import { useState, type SubmitEvent } from 'react';
import { Navigate, useLocation } from 'react-router';
import { SignInError } from '../auth/AuthClient';
import { useAuth } from '../auth/useAuth';
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

export function LoginPage() {
  const { session, client } = useAuth();
  const location = useLocation();
  const { t } = useTranslation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
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

  return (
    <main className="narrow">
      <h1>{t('login.title')}</h1>
      <form onSubmit={(event) => void submit(event)}>
        <div className="field">
          <label htmlFor="email">{t('login.email')}</label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="username"
            required
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);
            }}
          />
        </div>
        <div className="field">
          <label htmlFor="password">{t('login.password')}</label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(event) => {
              setPassword(event.target.value);
            }}
          />
        </div>
        {failure !== null && <p role="alert">{t(failure)}</p>}
        <button type="submit" disabled={pending}>
          {pending ? t('login.submitting') : t('login.submit')}
        </button>
      </form>
    </main>
  );
}
