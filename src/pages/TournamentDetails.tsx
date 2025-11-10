import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, MapPin, Calendar, Trophy, DollarSign, Users, Clock, FileText, GitBranch } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useLanguage, LanguageProvider } from '@/hooks/useLanguage';
import { useState } from 'react';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import Inscription from '@/components/Inscription';
import { CategoriesModal } from '@/components/CategoriesModal';
import { ScheduleModal } from '@/components/ScheduleModal';
import { RulesModal } from '@/components/RulesModal';
import tournament1 from '@/assets/tournament-1.jpg';
import tournament2 from '@/assets/tournament-2.jpg';

const TournamentDetailsContent = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useLanguage();
  const [categoriesOpen, setCategoriesOpen] = useState(false);
  const [scheduleOpen, setScheduleOpen] = useState(false);
  const [rulesOpen, setRulesOpen] = useState(false);

  // Tournament data (in a real app, this would come from an API)
  const tournaments = [
    {
      id: 'torneo-apertura-2025',
      image: tournament1,
      title: t('tournaments.tournament1.title'),
      date: t('tournaments.tournament1.date'),
      location: 'Hernandarias, Paraguay',
      category: 'Pro',
      court: 'Cancha Principal BT Arena',
      cost: '200.000 Gs por pareja',
      categories: [
        { name: 'Amateur', description: 'Para jugadores principiantes e intermedios' },
        { name: 'Mixto', description: 'Parejas conformadas por hombre y mujer' },
        { name: 'Pro', description: 'Para jugadores avanzados y competitivos' }
      ],
      prizes: [
        { position: 'Campeones', prize: 'Trofeo + 500.000 Gs' },
        { position: 'Vice-campeones', prize: 'Trofeo + 300.000 Gs' },
        { position: '3er lugar', prize: 'Medalla + 150.000 Gs' }
      ]
    },
    {
      id: 'copa-verano-2025',
      image: tournament2,
      title: t('tournaments.tournament2.title'),
      date: t('tournaments.tournament2.date'),
      location: 'Hernandarias, Paraguay',
      category: 'Amateur',
      court: 'Cancha Principal BT Arena',
      cost: '150.000 Gs por pareja',
      categories: [
        { name: 'Amateur', description: 'Para jugadores principiantes e intermedios' },
        { name: 'Mixto', description: 'Parejas conformadas por hombre y mujer' }
      ],
      prizes: [
        { position: 'Campeones', prize: 'Trofeo + 400.000 Gs' },
        { position: 'Vice-campeones', prize: 'Trofeo + 250.000 Gs' }
      ]
    }
  ];

  const tournament = tournaments.find(t => t.id === id);

  if (!tournament) {
    return (
      <div className="min-h-screen bg-background">
        <Header />
        <div className="container mx-auto px-4 py-20 text-center">
          <h1 className="text-4xl font-bold mb-4">{t('tournaments.notFound')}</h1>
          <Button onClick={() => navigate('/')}>
            {t('tournaments.backHome')}
          </Button>
        </div>
        <Footer />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background">
      <Header />
      
      <main className="pt-24 pb-20">
        <div className="container mx-auto px-4">
          <Button 
            variant="outline" 
            onClick={() => navigate('/')}
            className="mb-8"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            {t('tournaments.back')}
          </Button>

          {/* Tournament Header */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <div>
              <img 
                src={tournament.image} 
                alt={tournament.title}
                className="w-full h-[400px] object-cover rounded-2xl shadow-lg"
              />
            </div>
            <div className="flex flex-col justify-center">
              <h1 className="text-4xl md:text-5xl font-bold mb-4 text-foreground">
                {tournament.title}
              </h1>
              <div className="space-y-4 text-lg">
                <div className="flex items-center text-muted-foreground">
                  <Calendar className="mr-3 h-5 w-5 text-primary" />
                  {tournament.date}
                </div>
                <div className="flex items-center text-muted-foreground">
                  <MapPin className="mr-3 h-5 w-5 text-primary" />
                  {tournament.location}
                </div>
                <div className="flex items-center text-muted-foreground">
                  <Trophy className="mr-3 h-5 w-5 text-primary" />
                  {tournament.court}
                </div>
                <div className="flex items-center text-muted-foreground">
                  <DollarSign className="mr-3 h-5 w-5 text-primary" />
                  {tournament.cost}
                </div>
              </div>
            </div>
          </div>

          {/* Tournament Navigation Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            {/* Categories Card */}
            <Card 
              className="hover:shadow-xl hover:scale-105 transition-all cursor-pointer group"
              onClick={() => setCategoriesOpen(true)}
            >
              <CardContent className="p-8 flex flex-col items-center text-center gap-4">
                <div className="p-5 bg-primary/10 rounded-2xl group-hover:bg-primary/20 transition-colors">
                  <Users className="h-12 w-12 text-primary" />
                </div>
                <h3 className="font-bold text-xl">{t('tournaments.details.categories')}</h3>
              </CardContent>
            </Card>

            {/* Schedule Card */}
            <Card 
              className="hover:shadow-xl hover:scale-105 transition-all cursor-pointer group"
              onClick={() => setScheduleOpen(true)}
            >
              <CardContent className="p-8 flex flex-col items-center text-center gap-4">
                <div className="p-5 bg-primary/10 rounded-2xl group-hover:bg-primary/20 transition-colors">
                  <Clock className="h-12 w-12 text-primary" />
                </div>
                <h3 className="font-bold text-xl">Cronograma</h3>
              </CardContent>
            </Card>

            {/* Rules Card */}
            <Card 
              className="hover:shadow-xl hover:scale-105 transition-all cursor-pointer group"
              onClick={() => setRulesOpen(true)}
            >
              <CardContent className="p-8 flex flex-col items-center text-center gap-4">
                <div className="p-5 bg-primary/10 rounded-2xl group-hover:bg-primary/20 transition-colors">
                  <FileText className="h-12 w-12 text-primary" />
                </div>
                <h3 className="font-bold text-xl">Reglamento</h3>
              </CardContent>
            </Card>

            {/* Brackets Card */}
            <Card 
              className="hover:shadow-xl hover:scale-105 transition-all cursor-pointer group"
              onClick={() => navigate(`/torneo/${id}/llaves`)}
            >
              <CardContent className="p-8 flex flex-col items-center text-center gap-4">
                <div className="p-5 bg-primary/10 rounded-2xl group-hover:bg-primary/20 transition-colors">
                  <GitBranch className="h-12 w-12 text-primary" />
                </div>
                <h3 className="font-bold text-xl">Llaves</h3>
              </CardContent>
            </Card>
          </div>

          {/* Modals */}
          <CategoriesModal 
            open={categoriesOpen}
            onOpenChange={setCategoriesOpen}
            categories={tournament.categories}
            tournamentId={id}
          />
          <ScheduleModal 
            open={scheduleOpen}
            onOpenChange={setScheduleOpen}
          />
          <RulesModal 
            open={rulesOpen}
            onOpenChange={setRulesOpen}
          />

          {/* Inscription Form */}
          <div id="inscription-form">
            <Inscription tournamentId={id} tournamentName={tournament.title} />
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
};

const TournamentDetails = () => {
  return (
    <LanguageProvider>
      <TournamentDetailsContent />
    </LanguageProvider>
  );
};

export default TournamentDetails;
