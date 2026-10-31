import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent } from '../components/ui/Dialog';
import { useTranslation } from '../i18n';
import { createDetector } from './detector';

const scanInterval = 200;

/**
 * The camera, open in a window, looking for a barcode. When it reads one it hands the text over and
 * the window closes. The camera is released as soon as the window closes, so its light goes off.
 */
export function CameraScanner({
  onDetect,
  onClose,
}: {
  onDetect: (code: string) => void;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const video = useRef<HTMLVideoElement>(null);
  // The camera must not restart when the caller draws again with a new function.
  const latest = useRef(onDetect);
  useEffect(() => {
    latest.current = onDetect;
  }, [onDetect]);
  const [failed, setFailed] = useState(false);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const run = { stopped: false };
    // A function, so the compiler does not take the value for settled across the awaits.
    const isStopped = (): boolean => run.stopped;
    let stream: MediaStream | undefined;
    let timer: ReturnType<typeof setInterval> | undefined;

    async function start(): Promise<void> {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' } },
          audio: false,
        });
        const element = video.current;
        if (run.stopped || element === null) {
          return;
        }
        element.srcObject = stream;
        await element.play();
        const detector = await createDetector();
        if (isStopped()) {
          return;
        }
        setReady(true);
        let reading = false;
        timer = setInterval(() => {
          if (reading) {
            return;
          }
          reading = true;
          detector
            .detect(element)
            .then((code) => {
              if (code !== undefined && !isStopped()) {
                run.stopped = true;
                latest.current(code);
              }
            })
            .catch(() => undefined)
            .finally(() => {
              reading = false;
            });
        }, scanInterval);
      } catch {
        if (!isStopped()) {
          setFailed(true);
        }
      }
    }

    void start();

    return () => {
      run.stopped = true;
      clearInterval(timer);
      stream?.getTracks().forEach((track) => {
        track.stop();
      });
    };
  }, []);

  return (
    <Dialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
    >
      <DialogContent
        title={t('scan.camera.title')}
        description={t('scan.camera.description')}
        onOpenAutoFocus={(event) => {
          event.preventDefault();
        }}
      >
        {failed ? (
          <p role="alert" className="text-body-md text-bad">
            {t('scan.camera.denied')}
          </p>
        ) : (
          <>
            <video
              ref={video}
              muted
              playsInline
              className="aspect-[4/3] w-full rounded-panel bg-canvas object-cover"
            />
            {!ready && (
              <p role="status" className="text-body-md text-fg-secondary">
                {t('scan.camera.starting')}
              </p>
            )}
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}
