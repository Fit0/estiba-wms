/**
 * Textos en español, el idioma por defecto (ADR 0004). Cada clave existe también en `en.ts`, y un test
 * lo exige. Los términos siguen el lenguaje ubicuo de `docs/domain/ubiquitous-language.md`.
 */
export const es = {
  'app.name': 'Estiba WMS',
  'app.skipToContent': 'Saltar al contenido',
  'nav.main': 'Navegación principal',
  'nav.home': 'Inicio',
  'nav.audit': 'Auditoría',
  'session.restoring': 'Recuperando la sesión…',
  'session.signOut': 'Cerrar sesión',
  'session.signedInAs': 'Sesión iniciada como {{email}}',
  'role.operator': 'Operario',
  'role.supervisor': 'Supervisor',
  'role.admin': 'Administrador',
  'login.title': 'Iniciar sesión',
  'login.email': 'Correo electrónico',
  'login.password': 'Contraseña',
  'login.submit': 'Entrar',
  'login.submitting': 'Entrando…',
  'error.identity.credentials_invalid': 'El correo o la contraseña no son correctos.',
  'error.unexpected': 'No se ha podido iniciar la sesión. Inténtalo de nuevo.',
  'home.title': 'Inicio',
  'home.welcome': 'Bienvenido a {{tenant}}.',
  'audit.title': 'Registro de auditoría',
  'audit.intro': 'Aquí se verá todo lo que ha pasado en el almacén y quién lo hizo.',
  'forbidden.title': 'Sin permiso',
  'forbidden.body': 'Tu rol no permite ver esta página.',
  'notFound.title': 'Página no encontrada',
  'notFound.body': 'La página que buscas no existe.',
  'notFound.home': 'Volver al inicio',
} as const;
