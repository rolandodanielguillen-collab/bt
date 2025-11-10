import { Instagram, Facebook, Youtube } from 'lucide-react';
import { useLanguage } from '@/hooks/useLanguage';

const Footer = () => {
  const { t } = useLanguage();

  const scrollToSection = (sectionId: string) => {
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <footer className="bg-beach-dark text-primary-foreground py-12">
      <div className="container mx-auto px-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
          {/* Logo and Description */}
          <div>
            <div className="flex items-center gap-2 mb-4">
              <div className="bg-primary text-primary-foreground font-bold text-2xl px-3 py-1 rounded-lg">
                BT
              </div>
              <span className="font-semibold text-lg">Hernandarias</span>
            </div>
            <p className="text-primary-foreground/80">
              {t('contact.hours')}: {t('contact.hours.value')}
            </p>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="font-semibold text-lg mb-4">{t('footer.quick')}</h3>
            <nav className="flex flex-col gap-2">
              <button
                onClick={() => scrollToSection('hero')}
                className="text-primary-foreground/80 hover:text-primary-foreground transition-colors text-left"
              >
                {t('nav.home')}
              </button>
              <button
                onClick={() => scrollToSection('tournaments')}
                className="text-primary-foreground/80 hover:text-primary-foreground transition-colors text-left"
              >
                {t('nav.tournaments')}
              </button>
              <button
                onClick={() => scrollToSection('about')}
                className="text-primary-foreground/80 hover:text-primary-foreground transition-colors text-left"
              >
                {t('nav.about')}
              </button>
              <button
                onClick={() => scrollToSection('contact')}
                className="text-primary-foreground/80 hover:text-primary-foreground transition-colors text-left"
              >
                {t('nav.contact')}
              </button>
            </nav>
          </div>

          {/* Social Media */}
          <div>
            <h3 className="font-semibold text-lg mb-4">{t('footer.social')}</h3>
            <div className="flex gap-4">
              <a
                href="https://instagram.com"
                target="_blank"
                rel="noopener noreferrer"
                className="bg-primary/10 p-3 rounded-full hover:bg-primary hover:text-primary-foreground transition-colors"
              >
                <Instagram className="w-5 h-5" />
              </a>
              <a
                href="https://facebook.com"
                target="_blank"
                rel="noopener noreferrer"
                className="bg-primary/10 p-3 rounded-full hover:bg-primary hover:text-primary-foreground transition-colors"
              >
                <Facebook className="w-5 h-5" />
              </a>
              <a
                href="https://youtube.com"
                target="_blank"
                rel="noopener noreferrer"
                className="bg-primary/10 p-3 rounded-full hover:bg-primary hover:text-primary-foreground transition-colors"
              >
                <Youtube className="w-5 h-5" />
              </a>
            </div>
          </div>
        </div>

        {/* Copyright */}
        <div className="border-t border-primary-foreground/20 pt-8 text-center">
          <p className="text-primary-foreground/80">{t('footer.copyright')}</p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
