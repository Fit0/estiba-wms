import { Link } from 'react-router';
import { useTranslation } from '../i18n';

export function NotFoundPage() {
  const { t } = useTranslation();

  return (
    <section>
      <h1>{t('notFound.title')}</h1>
      <p>{t('notFound.body')}</p>
      <p>
        <Link to="/">{t('notFound.home')}</Link>
      </p>
    </section>
  );
}
