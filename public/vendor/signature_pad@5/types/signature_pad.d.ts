/**
 * The main idea and some parts of the code (e.g. drawing variable width Bézier curve) are taken from:
 * http://corner.squareup.com/2012/07/smoother-signatures.html
 *
 * Implementation of interpolation using cubic Bézier curves is taken from:
 * https://web.archive.org/web/20160323213433/http://www.benknowscode.com/2012/09/path-interpolation-using-cubic-bezier_9742.html
 *
 * Algorithm for approximated length of a Bézier curve is taken from:
 * http://www.lemoda.net/maths/bezier-length/index.html
 */
import { BasicPoint } from './point';
import { SignatureEventTarget } from './signature_event_target';
export interface SignatureEvent {
    event: MouseEvent | TouchEvent | PointerEvent;
    type: string;
    x: number;
    y: number;
    pressure: number;
}
export interface FromDataOptions {
    clear?: boolean;
}
export interface ToSVGOptions {
    includeBackgroundColor?: boolean;
}
export interface PointGroupOptions {
    dotSize: number;
    minWidth: number;
    maxWidth: number;
    penColor: string;
    velocityFilterWeight: number;
    /**
     * This is the globalCompositeOperation for the line.
     * *default: 'source-over'*
     * @see https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/globalCompositeOperation
     */
    compositeOperation: GlobalCompositeOperation;
}
export interface Options extends Partial<PointGroupOptions> {
    minDistance?: number;
    backgroundColor?: string;
    throttle?: number;
    canvasContextOptions?: CanvasRenderingContext2DSettings;
}
export interface PointGroup extends PointGroupOptions {
    points: BasicPoint[];
}
export default class SignaturePad extends SignatureEventTarget {
    private canvas;
    dotSize: number;
    minWidth: number;
    maxWidth: number;
    penColor: string;
    minDistance: number;
    velocityFilterWeight: number;
    compositeOperation: GlobalCompositeOperation;
    backgroundColor: string;
    throttle: number;
    canvasContextOptions: CanvasRenderingContext2DSettings;
    private _ctx;
    private _drawingStroke;
    private _isEmpty;
    private _lastPoints;
    private _data;
    private _lastVelocity;
    private _lastWidth;
    private _strokeMoveUpdate;
    constructor(canvas: HTMLCanvasElement, options?: Options);
    clear(): void;
    fromDataURL(dataUrl: string, options?: {
        ratio?: number;
        width?: number;
        height?: number;
        xOffset?: number;
        yOffset?: number;
    }): Promise<void>;
    toDataURL(type: 'image/svg+xml', encoderOptions?: ToSVGOptions): string;
    toDataURL(type?: string, encoderOptions?: number): string;
    on(): void;
    off(): void;
    private _getListenerFunctions;
    private _removeMoveUpEventListeners;
    isEmpty(): boolean;
    fromData(pointGroups: PointGroup[], { clear }?: FromDataOptions): void;
    toData(): PointGroup[];
    _isLeftButtonPressed(event: MouseEvent, only?: boolean): boolean;
    private _pointerEventToSignatureEvent;
    private _touchEventToSignatureEvent;
    private _handleMouseDown;
    private _handleMouseMove;
    private _handleMouseUp;
    private _handleTouchStart;
    private _handleTouchMove;
    private _handleTouchEnd;
    private _handlePointerDown;
    private _handlePointerMove;
    private _handlePointerUp;
    private _getPointGroupOptions;
    private _strokeBegin;
    private _strokeUpdate;
    private _strokeEnd;
    private _handlePointerEvents;
    private _handleMouseEvents;
    private _handleTouchEvents;
    private _reset;
    private _createPoint;
    private _addPoint;
    private _calculateCurveWidths;
    private _strokeWidth;
    private _drawCurveSegment;
    private _drawCurve;
    private _drawDot;
    private _fromData;
    toSVG({ includeBackgroundColor }?: ToSVGOptions): string;
}
