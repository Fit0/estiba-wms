import { BookText, CircleAlert, Eye, EyeOff, PackageCheck, Tags } from 'lucide-react';
import { useState, type SubmitEvent } from 'react';
import { Navigate, useLocation } from 'react-router';
import { SignInError } from '../auth/AuthClient';
import { useAuth } from '../auth/useAuth';
import { BrandMark } from '../components/BrandMark';
import { Button } from '../components/Button';
import { TextField } from '../components/TextField';
import { ThemeToggle } from '../components/ThemeToggle';
import { useTranslation, type MessageKey } from '../i18n';

/**
 * Where the user was going when the session asked them to sign in. It is a path of this app and
 * nothing else, so a crafted state cannot send anyone to another site.
 */
function destinationOf(state: unknown): string {
  const from =
    typeof state === 'object' && state !== null && 'from' in state ? state.from : undefined;

  return typeof from === 'string' && from.startsWith('/') && !from.startsWith('//') ? from : '/';
}

function errorKey(error: unknown): MessageKey {
  return error instanceof SignInError && error.code === 'identity.credentials_invalid'
    ? 'error.identity.credentials_invalid'
    : 'error.unexpected';
}

const highlights = [
  { icon: PackageCheck, text: 'login.hero.receiving' },
  { icon: BookText, text: 'login.hero.ledger' },
  { icon: Tags, text: 'login.hero.labels' },
] as const;

/**
 * Two panels, as in the example screen: what the app is for, and the form. The panel on the left
 * only says what the system really does, and it goes away on a narrow screen.
 */
export function LoginPage() {
  const { session, client } = useAuth();
  const location = useLocation();
  const { t } = useTranslation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordShown, setPasswordShown] = useState(false);
  const [failure, setFailure] = useState<MessageKey | null>(null);
  const [pending, setPending] = useState(false);

  if (session.status === 'signedIn') {
    return <Navigate to={destinationOf(location.state)} replace />;
  }

  async function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setFailure(null);
    try {
      await client.signIn(email.trim(), password);
    } catch (error: unknown) {
      setFailure(errorKey(error));
      setPending(false);
    }
  }

  const PasswordIcon = passwordShown ? EyeOff : Eye;

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <aside className="hidden flex-col justify-between border-r border-line bg-surface-1 p-margin-lg lg:flex">
        <BrandMark />
        <div className="grid max-w-xl gap-gutter-lg">
          <p className="font-display text-display">{t('login.hero.title')}</p>
          <ul className="grid gap-gutter">
            {highlights.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-3 text-body-lg text-fg-secondary">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-control bg-surface-2 text-accent">
                  <Icon aria-hidden="true" className="size-4" />
                </span>
                {t(text)}
              </li>
            ))}
          </ul>
        </div>
        <p className="text-body-sm text-fg-muted">{t('login.hero.footer')}</p>
      </aside>
      <main className="flex flex-col p-margin lg:p-margin-lg">
        <div className="flex items-center justify-between">
          <span className="lg:hidden">
            <BrandMark />
          </span>
          <span className="ml-auto">
            <ThemeToggle />
          </span>
        </div>
        <div className="m-auto grid w-full max-w-sm gap-gutter-lg py-margin-lg">
          <header className="grid gap-1">
            <h1 className="text-headline-lg">{t('login.title')}</h1>
            <p className="text-body-lg text-fg-secondary">{t('login.subtitle')}</p>
          </header>
          <form onSubmit={(event) => void submit(event)} className="grid gap-gutter-lg">
            <TextField
              label={t('login.email')}
              name="email"
              type="email"
              autoComplete="username"
              required
              value={email}
              onChange={(event) => {
                setEmail(event.target.value);
              }}
            />
            <TextField
              label={t('login.password')}
              name="password"
              type={passwordShown ? 'text' : 'password'}
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => {
                setPassword(event.target.value);
              }}
              action={
                <Button
                  variant="ghost"
                  icon
                  onClick={() => {
                    setPasswordShown(!passwordShown);
                  }}
                  aria-label={passwordShown ? t('login.hidePassword') : t('login.showPassword')}
                  aria-pressed={passwordShown}
                >
                  <PasswordIcon aria-hidden="true" className="size-4" />
                </Button>
              }
            />
            {failure !== null && (
              <p
                role="alert"
                className="flex items-start gap-2 rounded-control border border-bad-line bg-bad-fill p-3 text-body-md text-bad"
              >
                <CircleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                {t(failure)}
              </p>
            )}
            <Button type="submit" variant="primary" disabled={pending} className="w-full">
              {pending ? t('login.submitting') : t('login.submit')}
            </Button>
          </form>
        </div>
      </main>
    </div>
  );
}
