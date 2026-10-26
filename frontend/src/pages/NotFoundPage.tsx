import { Link } from 'react-router';
import { PageHeader } from '../components/PageHeader';
import { useTranslation } from '../i18n';

export function NotFoundPage() {
  const { t } = useTranslation();

  return (
    <>
      <PageHeader title={t('notFound.title')}>{t('notFound.body')}</PageHeader>
      <Link to="/" className="text-body-md font-medium text-accent underline">
        {t('notFound.home')}
      </Link>
    </>
  );
}
