import { chooseLanguage, useTranslation } from '../i18n';
import { Button } from './Button';

/**
 * Switches between Spanish and English. It shows the code of the language it will switch to, and its
 * name says so in full, in the language that is on now. The choice is remembered (ADR 0028).
 */
export function LanguageToggle() {
  const { language, t } = useTranslation();
  const next = language === 'es' ? 'en' : 'es';

  return (
    <Button
      variant="ghost"
      icon
      aria-label={language === 'es' ? t('language.toEnglish') : t('language.toSpanish')}
      onClick={() => void chooseLanguage(next)}
    >
      <span aria-hidden="true" className="text-label-md font-semibold uppercase">
        {next}
      </span>
    </Button>
  );
}
