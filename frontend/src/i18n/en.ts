import type { es } from './es';

/**
 * English texts. The type makes the compiler demand every key of `es.ts`, and no more.
 */
export const en: Record<keyof typeof es, string> = {
  'app.name': 'Estiba WMS',
  'app.skipToContent': 'Skip to content',
  'nav.main': 'Main navigation',
  'nav.home': 'Home',
  'nav.audit': 'Audit',
  'session.restoring': 'Restoring your session…',
  'session.signOut': 'Sign out',
  'session.signedInAs': 'Signed in as {{email}}',
  'role.operator': 'Operator',
  'role.supervisor': 'Supervisor',
  'role.admin': 'Administrator',
  'login.title': 'Sign in',
  'login.email': 'Email address',
  'login.password': 'Password',
  'login.submit': 'Sign in',
  'login.submitting': 'Signing in…',
  'error.identity.credentials_invalid': 'The email or the password is not correct.',
  'error.unexpected': 'Could not sign you in. Try again.',
  'home.title': 'Home',
  'home.welcome': 'Welcome to {{tenant}}.',
  'audit.title': 'Audit log',
  'audit.intro': 'Everything that happened in the warehouse, and who did it, will show up here.',
  'forbidden.title': 'No permission',
  'forbidden.body': 'Your role does not allow you to see this page.',
  'notFound.title': 'Page not found',
  'notFound.body': 'The page you are looking for does not exist.',
  'notFound.home': 'Back to home',
};
