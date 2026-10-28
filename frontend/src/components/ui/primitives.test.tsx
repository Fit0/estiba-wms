import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { Button } from '../Button';
import { Dialog, DialogContent, DialogTrigger } from './Dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from './DropdownMenu';
import { Select } from './Select';
import { Toaster, toast } from './Toaster';
import { Tooltip, TooltipProvider } from './Tooltip';

describe('Dialog', () => {
  function Example() {
    return (
      <Dialog>
        <DialogTrigger asChild>
          <Button>Abrir</Button>
        </DialogTrigger>
        <DialogContent title="Bloquear la ubicación" description="No admitirá movimientos.">
          <Button>Bloquear</Button>
        </DialogContent>
      </Dialog>
    );
  }

  it('opens with a name and a description, and keeps the focus inside', async () => {
    const user = userEvent.setup();
    render(<Example />);

    await user.click(screen.getByRole('button', { name: 'Abrir' }));
    const dialog = screen.getByRole('dialog', { name: 'Bloquear la ubicación' });

    expect(dialog).toHaveAccessibleDescription('No admitirá movimientos.');
    await user.tab();
    await user.tab();
    await user.tab();
    expect(dialog).toContainElement(document.activeElement as HTMLElement);
  });

  it('closes with Escape and gives the focus back to the button that opened it', async () => {
    const user = userEvent.setup();
    render(<Example />);
    const opener = screen.getByRole('button', { name: 'Abrir' });

    await user.click(opener);
    await user.keyboard('{Escape}');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(opener).toHaveFocus();
  });

  it('closes with its own button, named in the language of the screen', async () => {
    const user = userEvent.setup();
    render(<Example />);

    await user.click(screen.getByRole('button', { name: 'Abrir' }));
    await user.click(screen.getByRole('button', { name: 'Cerrar' }));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});

describe('DropdownMenu', () => {
  it('opens from the keyboard, moves with the arrows and runs the chosen action', async () => {
    const user = userEvent.setup();
    const chosen: string[] = [];
    render(
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button>Acciones</Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent>
          <DropdownMenuItem
            onSelect={() => {
              chosen.push('editar');
            }}
          >
            Editar
          </DropdownMenuItem>
          <DropdownMenuItem
            onSelect={() => {
              chosen.push('duplicar');
            }}
          >
            Duplicar
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>,
    );

    screen.getByRole('button', { name: 'Acciones' }).focus();
    await user.keyboard('{Enter}');
    expect(screen.getByRole('menu')).toBeInTheDocument();
    await user.keyboard('{ArrowDown}{Enter}');

    expect(chosen).toEqual(['duplicar']);
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  });
});

describe('Select', () => {
  function Example() {
    const [value, setValue] = useState<string | undefined>(undefined);

    return (
      <>
        <Select
          label="Estado del stock"
          value={value}
          onValueChange={setValue}
          placeholder="Elige un estado"
          options={[
            { value: 'available', label: 'Disponible' },
            { value: 'quarantine', label: 'Cuarentena' },
          ]}
        />
        <p>Elegido: {value ?? 'nada'}</p>
      </>
    );
  }

  it('has a visible label and a placeholder', () => {
    render(<Example />);

    expect(screen.getByRole('combobox', { name: 'Estado del stock' })).toHaveTextContent(
      'Elige un estado',
    );
  });

  it('chooses an option with the keyboard', async () => {
    const user = userEvent.setup();
    render(<Example />);

    screen.getByRole('combobox', { name: 'Estado del stock' }).focus();
    await user.keyboard('{Enter}');
    await user.click(await screen.findByRole('option', { name: 'Cuarentena' }));

    expect(screen.getByText('Elegido: quarantine')).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Estado del stock' })).toHaveTextContent(
      'Cuarentena',
    );
  });
});

describe('Tooltip', () => {
  it('shows its note when the control gets the keyboard focus', async () => {
    const user = userEvent.setup();
    render(
      <TooltipProvider delayDuration={0}>
        <Tooltip content="Copia el código">
          <Button>Copiar</Button>
        </Tooltip>
      </TooltipProvider>,
    );

    await user.tab();

    expect(await screen.findByRole('tooltip')).toHaveTextContent('Copia el código');
  });
});

describe('Toaster', () => {
  it('announces a notice without taking the focus', async () => {
    render(<Toaster />);

    toast.success('Ubicación bloqueada');

    expect(await screen.findByText('Ubicación bloqueada')).toBeInTheDocument();
    expect(document.body).toHaveFocus();
  });
});
