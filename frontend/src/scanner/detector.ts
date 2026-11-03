/**
 * Reads a barcode from a frame of the camera. Chrome and Android have `BarcodeDetector` built in;
 * where it is missing (Safari, Firefox) ZXing reads the frame instead. ZXing is a separate file that
 * the browser downloads only then, so the app does not carry it for those who do not need it.
 */
export interface Detector {
  /** The text of the first code in the frame, or `undefined` when there is none. */
  detect(video: HTMLVideoElement): Promise<string | undefined>;
}

/** The formats of what is in a warehouse: products, locations and logistic labels. */
const nativeFormats = [
  'ean_13',
  'ean_8',
  'upc_a',
  'upc_e',
  'code_128',
  'code_39',
  'itf',
  'qr_code',
  'data_matrix',
];

interface NativeDetector {
  detect(source: HTMLVideoElement): Promise<{ rawValue: string }[]>;
}

type NativeDetectorConstructor = new (options: { formats: string[] }) => NativeDetector;

function nativeConstructor(): NativeDetectorConstructor | undefined {
  const candidate: unknown = Reflect.get(window, 'BarcodeDetector');

  return typeof candidate === 'function' ? (candidate as NativeDetectorConstructor) : undefined;
}

/** Whether the browser lets a page open the camera at all (it needs HTTPS or `localhost`). */
export function canUseCamera(): boolean {
  return typeof navigator.mediaDevices === 'object' && 'getUserMedia' in navigator.mediaDevices;
}

async function zxingDetector(): Promise<Detector> {
  const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
    import('@zxing/browser'),
    import('@zxing/library'),
  ]);
  const reader = new BrowserMultiFormatReader(
    new Map([
      [
        DecodeHintType.POSSIBLE_FORMATS,
        [
          BarcodeFormat.EAN_13,
          BarcodeFormat.EAN_8,
          BarcodeFormat.UPC_A,
          BarcodeFormat.UPC_E,
          BarcodeFormat.CODE_128,
          BarcodeFormat.CODE_39,
          BarcodeFormat.ITF,
          BarcodeFormat.QR_CODE,
          BarcodeFormat.DATA_MATRIX,
        ],
      ],
    ]),
  );
  const canvas = document.createElement('canvas');

  return {
    detect: (video) => {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const context = canvas.getContext('2d');
      if (context === null || canvas.width === 0) {
        return Promise.resolve(undefined);
      }
      context.drawImage(video, 0, 0);
      try {
        return Promise.resolve(reader.decodeFromCanvas(canvas).getText());
      } catch {
        // ZXing throws when the frame has no code in it, which is the usual case.
        return Promise.resolve(undefined);
      }
    },
  };
}

export async function createDetector(): Promise<Detector> {
  const Native = nativeConstructor();
  if (Native === undefined) {
    return zxingDetector();
  }
  const native = new Native({ formats: nativeFormats });

  return {
    detect: async (video) => (await native.detect(video))[0]?.rawValue,
  };
}
