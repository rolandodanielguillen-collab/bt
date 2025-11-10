import { useState } from 'react';
import { MessageCircle } from 'lucide-react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './ui/select';
import { Checkbox } from './ui/checkbox';
import { Label } from './ui/label';
import { useLanguage } from '@/hooks/useLanguage';
import { toast } from 'sonner';

interface InscriptionProps {
  tournamentId?: string;
  tournamentName?: string;
}

const Inscription = ({ tournamentId, tournamentName }: InscriptionProps) => {
  const { t } = useLanguage();
  const [formData, setFormData] = useState({
    player1FirstName: '',
    player1LastName: '',
    player1Document: '',
    player1Phone: '',
    player1Email: '',
    player1City: '',
    player2FirstName: '',
    player2LastName: '',
    player2Document: '',
    player2Phone: '',
    player2Email: '',
    player2City: '',
    category: '',
    acceptTerms: false,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    // Validation
    const requiredFields = [
      'player1FirstName', 'player1LastName', 'player1Document', 'player1Phone', 
      'player1Email', 'player1City', 'player2FirstName', 'player2LastName', 
      'player2Document', 'player2Phone', 'player2Email', 'player2City', 'category'
    ];
    
    const hasEmptyFields = requiredFields.some(field => !formData[field as keyof typeof formData]);
    
    if (hasEmptyFields) {
      toast.error(t('inscription.form.required'));
      return;
    }

    if (!formData.acceptTerms) {
      toast.error(t('inscription.form.terms'));
      return;
    }

    // WhatsApp message
    const phone = '595981189807';
    const tournamentInfo = tournamentName ? `*Torneo:* ${tournamentName}\n\n` : '';
    const message = encodeURIComponent(
      `*Nueva Inscripción - BT Hernandarias*\n\n` +
      tournamentInfo +
      `*JUGADOR 1*\n` +
      `Nombre: ${formData.player1FirstName} ${formData.player1LastName}\n` +
      `Documento: ${formData.player1Document}\n` +
      `Celular: ${formData.player1Phone}\n` +
      `Email: ${formData.player1Email}\n` +
      `Ciudad: ${formData.player1City}\n\n` +
      `*JUGADOR 2*\n` +
      `Nombre: ${formData.player2FirstName} ${formData.player2LastName}\n` +
      `Documento: ${formData.player2Document}\n` +
      `Celular: ${formData.player2Phone}\n` +
      `Email: ${formData.player2Email}\n` +
      `Ciudad: ${formData.player2City}\n\n` +
      `*Categoría:* ${formData.category}\n` +
      `*Términos aceptados:* Sí`
    );
    
    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
    toast.success('¡Redirigiendo a WhatsApp!');
    
    // Reset form
    setFormData({
      player1FirstName: '',
      player1LastName: '',
      player1Document: '',
      player1Phone: '',
      player1Email: '',
      player1City: '',
      player2FirstName: '',
      player2LastName: '',
      player2Document: '',
      player2Phone: '',
      player2Email: '',
      player2City: '',
      category: '',
      acceptTerms: false,
    });
  };

  const handleWhatsAppDirect = () => {
    const phone = '595981189807';
    const message = encodeURIComponent('¡Hola! Me gustaría más información sobre inscripciones a torneos.');
    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
  };

  return (
    <section id="inscription" className="py-20 bg-gradient-to-br from-primary/5 to-secondary/5">
      <div className="container mx-auto px-4">
        <div className="max-w-3xl mx-auto text-center mb-12">
          <h2 className="text-4xl md:text-5xl font-bold mb-4">
            {t('inscription.title')}
          </h2>
          <p className="text-lg text-muted-foreground">
            {t('inscription.subtitle')}
          </p>
        </div>

        <div className="max-w-4xl mx-auto">
          <form onSubmit={handleSubmit} className="space-y-8 bg-card p-8 rounded-2xl shadow-lg border">
            {/* Player 1 Section */}
            <div className="space-y-4">
              <h3 className="text-2xl font-bold text-primary border-b-2 border-primary pb-2">
                {t('inscription.form.player1')}
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="player1FirstName">{t('inscription.form.firstName')} *</Label>
                  <Input
                    id="player1FirstName"
                    type="text"
                    value={formData.player1FirstName}
                    onChange={(e) => setFormData({ ...formData, player1FirstName: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player1LastName">{t('inscription.form.lastName')} *</Label>
                  <Input
                    id="player1LastName"
                    type="text"
                    value={formData.player1LastName}
                    onChange={(e) => setFormData({ ...formData, player1LastName: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player1Document">{t('inscription.form.document')} *</Label>
                  <Input
                    id="player1Document"
                    type="text"
                    value={formData.player1Document}
                    onChange={(e) => setFormData({ ...formData, player1Document: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player1Phone">{t('inscription.form.phone')} *</Label>
                  <Input
                    id="player1Phone"
                    type="tel"
                    value={formData.player1Phone}
                    onChange={(e) => setFormData({ ...formData, player1Phone: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player1Email">{t('inscription.form.email')} *</Label>
                  <Input
                    id="player1Email"
                    type="email"
                    value={formData.player1Email}
                    onChange={(e) => setFormData({ ...formData, player1Email: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player1City">{t('inscription.form.city')} *</Label>
                  <Input
                    id="player1City"
                    type="text"
                    value={formData.player1City}
                    onChange={(e) => setFormData({ ...formData, player1City: e.target.value })}
                    required
                  />
                </div>
              </div>
            </div>

            {/* Player 2 Section */}
            <div className="space-y-4">
              <h3 className="text-2xl font-bold text-secondary border-b-2 border-secondary pb-2">
                {t('inscription.form.player2')}
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="player2FirstName">{t('inscription.form.firstName')} *</Label>
                  <Input
                    id="player2FirstName"
                    type="text"
                    value={formData.player2FirstName}
                    onChange={(e) => setFormData({ ...formData, player2FirstName: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player2LastName">{t('inscription.form.lastName')} *</Label>
                  <Input
                    id="player2LastName"
                    type="text"
                    value={formData.player2LastName}
                    onChange={(e) => setFormData({ ...formData, player2LastName: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player2Document">{t('inscription.form.document')} *</Label>
                  <Input
                    id="player2Document"
                    type="text"
                    value={formData.player2Document}
                    onChange={(e) => setFormData({ ...formData, player2Document: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player2Phone">{t('inscription.form.phone')} *</Label>
                  <Input
                    id="player2Phone"
                    type="tel"
                    value={formData.player2Phone}
                    onChange={(e) => setFormData({ ...formData, player2Phone: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player2Email">{t('inscription.form.email')} *</Label>
                  <Input
                    id="player2Email"
                    type="email"
                    value={formData.player2Email}
                    onChange={(e) => setFormData({ ...formData, player2Email: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="player2City">{t('inscription.form.city')} *</Label>
                  <Input
                    id="player2City"
                    type="text"
                    value={formData.player2City}
                    onChange={(e) => setFormData({ ...formData, player2City: e.target.value })}
                    required
                  />
                </div>
              </div>
            </div>

            {/* Category Selection */}
            <div className="space-y-2">
              <Label htmlFor="category">{t('inscription.form.category')} *</Label>
              <Select value={formData.category} onValueChange={(value) => setFormData({ ...formData, category: value })}>
                <SelectTrigger id="category">
                  <SelectValue placeholder={t('inscription.form.category')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="amateur">{t('inscription.form.category.amateur')}</SelectItem>
                  <SelectItem value="mixed">{t('inscription.form.category.mixed')}</SelectItem>
                  <SelectItem value="pro">{t('inscription.form.category.pro')}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Terms and Conditions */}
            <div className="flex items-start space-x-3 p-4 bg-muted/50 rounded-lg">
              <Checkbox
                id="terms"
                checked={formData.acceptTerms}
                onCheckedChange={(checked) => setFormData({ ...formData, acceptTerms: checked as boolean })}
                required
              />
              <div className="space-y-1 leading-none">
                <Label 
                  htmlFor="terms"
                  className="text-sm font-medium leading-relaxed cursor-pointer"
                >
                  {t('inscription.form.terms')} *
                </Label>
                <p className="text-xs text-muted-foreground">
                  <a href="#" className="text-primary hover:underline">
                    {t('inscription.form.termsLink')}
                  </a>
                </p>
              </div>
            </div>

            <Button type="submit" size="lg" className="w-full text-lg">
              {t('inscription.form.submit')}
            </Button>
          </form>

          <div className="mt-8 text-center">
            <p className="text-muted-foreground mb-4">o</p>
            <Button
              size="lg"
              variant="outline"
              onClick={handleWhatsAppDirect}
              className="w-full border-2 border-secondary hover:bg-secondary hover:text-secondary-foreground"
            >
              <MessageCircle className="mr-2 h-5 w-5" />
              {t('inscription.whatsapp')}
            </Button>
          </div>
        </div>
      </div>
    </section>
  );
};

export default Inscription;
