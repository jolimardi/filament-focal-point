/*
 * Popup "Point focal" (JoliMardi\FilamentFocalPoint\MyFocalPoint) : composant Alpine chargé à l'ouverture de la popup
 * (x-load). On place le point en cliquant ou en glissant sur l'image (souris, doigt, stylet).
 *
 * state = {x, y} en % entiers, ou null (pas de point : centre). cropRatio = ratio de la conversion recadrée côté
 * serveur (null si elle n'est pas recadrée). Les aperçus refont les deux recadrages du front :
 * 1. la conversion autour du point, même calcul que spatie/image et FocalPoint::positionInCrop() ;
 * 2. le CSS cover dans le cadre du front, avec la position du point dans la conversion (FocalPoint::position()).
 */
export default function focalPointPicker({ state, cropRatio }) {
    return {
        state,
        cropRatio,
        size: null, // dimensions de l'image, connues à son chargement
        dragging: false,

        get point() {
            return this.state && this.state.x != null && this.state.y != null ? this.state : null
        },

        init() {
            // Image déjà en cache : chargée avant l'initialisation du composant
            const image = this.$refs.surface.querySelector('img')
            if (image?.complete && image.naturalWidth) this.loaded({ target: image })
        },

        loaded(event) {
            this.size = { w: event.target.naturalWidth, h: event.target.naturalHeight }
        },

        // Largeur de l'image : celle de la popup, sans dépasser 26rem de haut. Le cadre a ainsi exactement la taille de
        // l'image (repère des clics et du point).
        surfaceStyle() {
            return this.size ? { width: `min(100%, ${(26 * this.size.w / this.size.h).toFixed(2)}rem)` } : {}
        },

        // Un objet, pas une chaîne : une chaîne remplacerait tout l'attribut style
        markerStyle() {
            const { x, y } = this.point ?? { x: 50, y: 50 }
            return { left: `${x}%`, top: `${y}%` }
        },

        place(event) {
            const rect = this.$refs.surface.getBoundingClientRect()
            if (!rect.width || !rect.height) return
            const percent = (value) => Math.round(Math.min(1, Math.max(0, value)) * 100)
            this.state = {
                x: percent((event.clientX - rect.left) / rect.width),
                y: percent((event.clientY - rect.top) / rect.height),
            }
        },

        start(event) {
            if (event.button) return
            this.dragging = true
            this.$refs.surface.setPointerCapture?.(event.pointerId)
            this.place(event)
        },

        move(event) {
            if (this.dragging) this.place(event)
        },

        stop(event) {
            this.dragging = false
            this.$refs.surface.releasePointerCapture?.(event.pointerId)
        },

        reset() {
            this.state = null
        },

        // Aperçu dans un cadre de ratio `ratio` : zone de l'image d'origine visible sur le front, affichée en fond
        previewStyle(ratio) {
            if (!this.size) return {}

            const { w: W, h: H } = this.size
            const { x: fx, y: fy } = this.point ?? { x: 50, y: 50 }
            const px = W * fx / 100, py = H * fy / 100
            let zone = { x: 0, y: 0, w: W, h: H }, position = { x: fx, y: fy }

            if (this.cropRatio) {
                const wider = W / H > this.cropRatio
                const w = wider ? Math.round(H * this.cropRatio) : W
                const h = wider ? H : Math.round(W / this.cropRatio)
                const x = Math.max(0, Math.min(Math.trunc(px - w / 2), W - w))
                const y = Math.max(0, Math.min(Math.trunc(py - h / 2), H - h))
                zone = { x, y, w, h }
                position = { x: (px - x) / w * 100, y: (py - y) / h * 100 }
            }

            if (zone.w / zone.h > ratio) {
                const visible = zone.h * ratio
                zone.x += (zone.w - visible) * position.x / 100
                zone.w = visible
            } else {
                const visible = zone.w / ratio
                zone.y += (zone.h - visible) * position.y / 100
                zone.h = visible
            }

            const offset = (start, visible, total) => total - visible > 0.5 ? start / (total - visible) * 100 : 0

            return {
                backgroundSize: `${W / zone.w * 100}% auto`,
                backgroundPosition: `${offset(zone.x, zone.w, W)}% ${offset(zone.y, zone.h, H)}%`,
            }
        },
    }
}
