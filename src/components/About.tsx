import { useLanguage } from '@/hooks/useLanguage';
import aboutImage from '@/assets/about-court.jpg';

const About = () => {
  const { t } = useLanguage();

  return (
    <section id="about" className="py-20 bg-muted/30">
      <div className="container mx-auto px-4">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <div className="order-2 lg:order-1">
            <h2 className="text-4xl md:text-5xl font-bold mb-6">
              {t('about.title')}
            </h2>
            <p className="text-lg text-muted-foreground leading-relaxed mb-6">
              {t('about.text')}
            </p>
            <div className="grid grid-cols-2 gap-6 mt-8">
              <div className="text-center p-6 bg-card rounded-lg border-2 border-primary/20">
                <div className="text-4xl font-bold text-primary mb-2">100+</div>
                <div className="text-sm text-muted-foreground">Participantes</div>
              </div>
              <div className="text-center p-6 bg-card rounded-lg border-2 border-secondary/20">
                <div className="text-4xl font-bold text-secondary mb-2">50+</div>
                <div className="text-sm text-muted-foreground">Torneos</div>
              </div>
            </div>
          </div>
          <div className="order-1 lg:order-2">
            <div className="relative">
              <div className="absolute -inset-4 bg-gradient-to-r from-primary/20 to-secondary/20 rounded-2xl blur-xl" />
              <img
                src={aboutImage}
                alt="Beach Tennis Court"
                className="relative rounded-2xl shadow-2xl w-full h-[400px] object-cover"
              />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default About;
