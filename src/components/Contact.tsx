import { MapPin, Phone, Mail, Clock, MessageCircle } from 'lucide-react';
import { Button } from './ui/button';
import { Card, CardContent } from './ui/card';
import { useLanguage } from '@/hooks/useLanguage';

const Contact = () => {
  const { t } = useLanguage();

  const handleWhatsApp = () => {
    const phone = '595981189807';
    const message = encodeURIComponent('¡Hola! Me gustaría obtener más información.');
    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
  };

  const handleCall = () => {
    window.location.href = 'tel:+595981189807';
  };

  const handleEmail = () => {
    window.location.href = 'mailto:info@bt.com.py';
  };

  return (
    <section id="contact" className="py-20 bg-background">
      <div className="container mx-auto px-4">
        <h2 className="text-4xl md:text-5xl font-bold text-center mb-4">
          {t('contact.title')}
        </h2>
        <p className="text-center text-muted-foreground mb-12 text-lg">
          {t('contact.info')}
        </p>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl mx-auto">
          {/* Contact Info */}
          <div className="space-y-6">
            <Card className="hover:shadow-lg transition-shadow">
              <CardContent className="p-6 flex items-start gap-4">
                <div className="bg-primary/10 p-3 rounded-full">
                  <MapPin className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <h3 className="font-semibold mb-1 text-lg">Ubicación</h3>
                  <p className="text-muted-foreground">{t('contact.location')}</p>
                </div>
              </CardContent>
            </Card>

            <Card className="hover:shadow-lg transition-shadow cursor-pointer" onClick={handleCall}>
              <CardContent className="p-6 flex items-start gap-4">
                <div className="bg-primary/10 p-3 rounded-full">
                  <Phone className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <h3 className="font-semibold mb-1 text-lg">Teléfono</h3>
                  <p className="text-muted-foreground">{t('contact.phone')}</p>
                </div>
              </CardContent>
            </Card>

            <Card className="hover:shadow-lg transition-shadow cursor-pointer" onClick={handleEmail}>
              <CardContent className="p-6 flex items-start gap-4">
                <div className="bg-primary/10 p-3 rounded-full">
                  <Mail className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <h3 className="font-semibold mb-1 text-lg">Email</h3>
                  <p className="text-muted-foreground">{t('contact.email')}</p>
                </div>
              </CardContent>
            </Card>

            <Card className="hover:shadow-lg transition-shadow">
              <CardContent className="p-6 flex items-start gap-4">
                <div className="bg-primary/10 p-3 rounded-full">
                  <Clock className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <h3 className="font-semibold mb-1 text-lg">{t('contact.hours')}</h3>
                  <p className="text-muted-foreground">{t('contact.hours.value')}</p>
                </div>
              </CardContent>
            </Card>

            <Button
              size="lg"
              onClick={handleWhatsApp}
              className="w-full bg-secondary hover:bg-secondary-hover text-secondary-foreground"
            >
              <MessageCircle className="mr-2 h-5 w-5" />
              {t('contact.whatsapp')}
            </Button>
          </div>

          {/* Map */}
          <div className="h-full min-h-[400px]">
            <div className="bg-muted rounded-2xl overflow-hidden h-full shadow-lg">
              <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d57682.57845286316!2d-54.653827!3d-25.407778!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94f69bc8f06ce2fb%3A0x6d84830c7cc40e0c!2sHernandarias%2C%20Paraguay!5e0!3m2!1sen!2s!4v1234567890123!5m2!1sen!2s"
                width="100%"
                height="100%"
                style={{ border: 0 }}
                allowFullScreen
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                title="BT Hernandarias Location"
              />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default Contact;
