import { Trophy, Award, Users, ShieldCheck, Target, Zap, Star, Heart, Crown } from 'lucide-react';
import { Button } from './ui/button';
import { Card, CardHeader, CardTitle, CardDescription } from './ui/card';
import { useLanguage } from '@/hooks/useLanguage';
import { Carousel, CarouselContent, CarouselItem } from './ui/carousel';
import Autoplay from 'embla-carousel-autoplay';

const Services = () => {
  const { t } = useLanguage();

  const scrollToSection = (sectionId: string) => {
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  const logos = [
    { icon: ShieldCheck, color: 'text-primary' },
    { icon: Target, color: 'text-secondary' },
    { icon: Zap, color: 'text-primary' },
    { icon: Star, color: 'text-secondary' },
    { icon: Heart, color: 'text-primary' },
    { icon: Crown, color: 'text-secondary' },
    { icon: Trophy, color: 'text-primary' },
    { icon: Award, color: 'text-secondary' },
    { icon: Users, color: 'text-primary' },
  ];

  const services = [
    {
      icon: Trophy,
      title: t('services.tournaments.title'),
      description: t('services.tournaments.desc'),
    },
    {
      icon: Award,
      title: t('services.regional.title'),
      description: t('services.regional.desc'),
    },
    {
      icon: Users,
      title: t('services.competitive.title'),
      description: t('services.competitive.desc'),
    },
  ];

  return (
    <section className="py-20 bg-background">
      <div className="container mx-auto px-4">
        <div className="mb-12">
          <Carousel
            opts={{ align: 'start', loop: true }}
            plugins={[
              Autoplay({
                delay: 2000,
              }),
            ]}
            className="w-full max-w-5xl mx-auto mb-8"
          >
            <CarouselContent>
              {logos.map((logo, index) => (
                <CarouselItem key={index} className="basis-1/3 md:basis-1/5 lg:basis-1/6">
                  <div className="flex items-center justify-center p-2">
                    <Card className="overflow-hidden hover:shadow-lg transition-all duration-300 w-20 h-20">
                      <div className="relative w-full h-full bg-gradient-to-br from-primary/5 to-secondary/5 flex items-center justify-center hover:scale-105 transition-transform duration-300">
                        <logo.icon className={`w-10 h-10 ${logo.color}`} />
                      </div>
                    </Card>
                  </div>
                </CarouselItem>
              ))}
            </CarouselContent>
          </Carousel>
        </div>
        
        <h2 className="text-4xl md:text-5xl font-bold text-center mb-12">
          {t('services.title')}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
          {services.map((service, index) => (
            <Card
              key={index}
              className="border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1"
            >
              <CardHeader className="text-center">
                <div className="mx-auto mb-4 w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center">
                  <service.icon className="w-8 h-8 text-primary" />
                </div>
                <CardTitle className="text-2xl mb-3">{service.title}</CardTitle>
                <CardDescription className="text-base">{service.description}</CardDescription>
              </CardHeader>
            </Card>
          ))}
        </div>
        <div className="text-center">
          <Button
            size="lg"
            onClick={() => scrollToSection('tournaments')}
            className="px-8 py-6 text-lg"
          >
            {t('services.cta')}
          </Button>
        </div>
      </div>
    </section>
  );
};

export default Services;
