import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CameraScanner } from './CameraScanner';
import { canUseCamera, createDetector } from './detector';
import { ScanField } from './ScanField';

const zxing = vi.hoisted(() => ({ text: undefined as string | undefined }));

vi.mock('@zxing/browser', () => ({
  BrowserMultiFormatReader: class {
    decodeFromCanvas() {
      if (zxing.text === undefined) {
        throw new Error('NotFoundException');
      }

      return { getText: () => zxing.text };
    }
  },
}));

function fakeVideo(): HTMLVideoElement {
  const video = document.createElement('video');
  Object.defineProperty(video, 'videoWidth', { value: 640 });
  Object.defineProperty(video, 'videoHeight', { value: 480 });

  return video;
}

afterEach(() => {
  Reflect.deleteProperty(window, 'BarcodeDetector');
  Reflect.deleteProperty(navigator, 'mediaDevices');
  vi.restoreAllMocks();
});

describe('createDetector', () => {
  it('uses the BarcodeDetector of the browser when there is one', async () => {
    const formats: string[][] = [];
    Object.defineProperty(window, 'BarcodeDetector', {
      configurable: true,
      value: class {
        constructor(options: { formats: string[] }) {
          formats.push(options.formats);
        }
        detect() {
          return Promise.resolve([{ rawValue: '8412345678905' }]);
        }
      },
    });

    const detector = await createDetector();

    expect(await detector.detect(fakeVideo())).toBe('8412345678905');
    expect(formats[0]).toContain('ean_13');
    expect(formats[0]).toContain('code_128');
  });

  it('says there is no code when the frame has none', async () => {
    Object.defineProperty(window, 'BarcodeDetector', {
      configurable: true,
      value: class {
        detect() {
          return Promise.resolve([]);
        }
      },
    });

    expect(await (await createDetector()).detect(fakeVideo())).toBeUndefined();
  });

  it('falls back to ZXing when the browser has no BarcodeDetector', async () => {
    const context = { drawImage: vi.fn() } as unknown as CanvasRenderingContext2D;
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(context);
    const detector = await createDetector();

    zxing.text = undefined;
    expect(await detector.detect(fakeVideo())).toBeUndefined();
    zxing.text = '8412345678905';
    expect(await detector.detect(fakeVideo())).toBe('8412345678905');
  });
});

describe('canUseCamera', () => {
  it('is true only where the browser can open a camera', () => {
    expect(canUseCamera()).toBe(false);
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia: vi.fn() },
    });
    expect(canUseCamera()).toBe(true);
  });
});

describe('ScanField', () => {
  it('has no camera button where there is no camera', () => {
    render(<ScanField label="Ubicación" value="" onScan={vi.fn()} />);

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('hands over what is typed', () => {
    const onScan = vi.fn();
    render(<ScanField label="Ubicación" value="" onScan={onScan} />);

    fireEvent.change(screen.getByLabelText('Ubicación'), { target: { value: 'A-01' } });

    expect(onScan).toHaveBeenCalledWith('A-01');
  });
});

describe('CameraScanner', () => {
  const stop = vi.fn();

  beforeEach(() => {
    stop.mockReset();
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia: vi.fn(() => Promise.resolve({ getTracks: () => [{ stop }] })) },
    });
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue(undefined);
  });

  it('reads a code, hands it over and releases the camera', async () => {
    Object.defineProperty(window, 'BarcodeDetector', {
      configurable: true,
      value: class {
        detect() {
          return Promise.resolve([{ rawValue: 'A-01-01' }]);
        }
      },
    });
    const onDetect = vi.fn();
    const { unmount } = render(<CameraScanner onDetect={onDetect} onClose={vi.fn()} />);

    await vi.waitFor(() => {
      expect(onDetect).toHaveBeenCalledWith('A-01-01');
    });
    unmount();

    expect(stop).toHaveBeenCalled();
  });

  it('explains when the camera cannot be used', async () => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia: vi.fn(() => Promise.reject(new Error('NotAllowedError'))) },
    });

    render(<CameraScanner onDetect={vi.fn()} onClose={vi.fn()} />);

    expect(await screen.findByRole('alert')).toHaveTextContent('No se puede usar la cámara');
  });
});
