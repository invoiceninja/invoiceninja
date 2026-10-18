import SignaturePad from 'signature_pad';

export function createSignature(canvas, onChange = () => {}) {
    const pad = new SignaturePad(canvas);
    let width = 0;
    let height = 0;

    function resize() {
        const nextWidth = canvas.clientWidth;
        const nextHeight = canvas.clientHeight;
        if (!nextWidth || !nextHeight) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        if (canvas.width === Math.round(nextWidth * ratio) && canvas.height === Math.round(nextHeight * ratio)) return;
        const strokes = pad.toData().map(stroke => ({
            ...stroke,
            points: stroke.points.map(point => ({
                ...point,
                x: width ? point.x * nextWidth / width : point.x,
                y: height ? point.y * nextHeight / height : point.y,
            })),
        }));
        canvas.width = Math.round(nextWidth * ratio);
        canvas.height = Math.round(nextHeight * ratio);
        canvas.getContext('2d').scale(ratio, ratio);
        pad.clear();
        pad.fromData(strokes);
        width = nextWidth;
        height = nextHeight;
        onChange(!pad.isEmpty());
    }

    const onEndStroke = () => onChange(!pad.isEmpty());
    pad.addEventListener('endStroke', onEndStroke);
    const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(resize) : null;
    if (observer) observer.observe(canvas);
    else window.addEventListener('resize', resize);
    resize();

    return {
        pad,
        resize,
        clear() { pad.clear(); onChange(false); },
        destroy() {
            observer?.disconnect();
            window.removeEventListener('resize', resize);
            pad.removeEventListener('endStroke', onEndStroke);
            pad.off();
        },
    };
}

export function signatureComponent() {
    let signature;
    return {
        signed: false,
        init() {
            this.$nextTick(() => {
                signature = createSignature(this.$refs.canvas, signed => this.signed = signed);
            });
        },
        clear() { signature.clear(); },
        save() {
            if (!signature.pad.isEmpty()) {
                this.$wire.dispatch('signature-captured', { base64: signature.pad.toDataURL() });
            }
        },
        destroy() { signature?.destroy(); },
    };
}
