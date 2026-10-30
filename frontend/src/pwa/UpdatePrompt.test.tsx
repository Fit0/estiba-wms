import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Toaster } from '../components/ui/Toaster';
import { UpdatePrompt } from './UpdatePrompt';

const registration = vi.hoisted(() => ({
  offlineReady: false,
  needRefresh: false,
  update: vi.fn<(reload?: boolean) => Promise<void>>(),
}));

vi.mock('virtual:pwa-register/react', () => ({
  useRegisterSW: () => ({
    offlineReady: [registration.offlineReady, vi.fn()],
    needRefresh: [registration.needRefresh, vi.fn()],
    updateServiceWorker: registration.update,
  }),
}));

function show() {
  render(
    <>
      <UpdatePrompt />
      <Toaster />
    </>,
  );
}

describe('UpdatePrompt', () => {
  beforeEach(() => {
    registration.offlineReady = false;
    registration.needRefresh = false;
    registration.update.mockReset();
  });

  it('says nothing while there is nothing to say', () => {
    show();

    expect(screen.queryByText(/versión nueva|sin conexión/)).not.toBeInTheDocument();
  });

  it('tells the person the app can be opened without a connection', async () => {
    registration.offlineReady = true;
    show();

    expect(await screen.findByText('La app ya se puede abrir sin conexión.')).toBeInTheDocument();
  });

  it('offers the new version and takes it only when asked', async () => {
    registration.needRefresh = true;
    show();

    expect(await screen.findByText('Hay una versión nueva de la app.')).toBeInTheDocument();
    expect(registration.update).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Actualizar' }));

    expect(registration.update).toHaveBeenCalledWith(true);
  });
});
