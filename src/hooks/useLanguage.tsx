import { useState, createContext, useContext, ReactNode } from 'react';

type Language = 'es' | 'pt';

interface LanguageContextType {
  language: Language;
  toggleLanguage: () => void;
  t: (key: string) => string;
}

const translations = {
  es: {
    // Header
    'nav.home': 'Inicio',
    'nav.tournaments': 'Torneos',
    'nav.about': 'Nosotros',
    'nav.contact': 'Contacto',
    'nav.signup': 'Inscripción',
    'cta.signup': 'Inscribirme al torneo',
    
    // Hero
    'hero.title': 'Vive la pasión del Beach Tennis en Hernandarias',
    'hero.subtitle': 'Organizamos los mejores torneos de beach tennis.',
    'hero.cta.primary': 'Inscribirme al torneo',
    'hero.cta.secondary': 'Conocer más',
    
    // Services
    'services.title': 'Lo que ofrecemos',
    'services.tournaments.title': 'Torneos Locales',
    'services.tournaments.desc': 'Organizamos eventos competitivos para jugadores de todos los niveles en Hernandarias.',
    'services.regional.title': 'Torneos Regionales',
    'services.regional.desc': 'Eventos de mayor escala que reúnen a los mejores jugadores de la región.',
    'services.competitive.title': 'Ambiente Competitivo',
    'services.competitive.desc': 'Fomentamos el espíritu deportivo y la competencia sana en cada torneo.',
    'services.cta': 'Ver próximos torneos',
    
    // About
    'about.title': 'Sobre BT Hernandarias',
    'about.text': 'BT (Beach Tennis Hernandarias) es una organización dedicada a impulsar el crecimiento del beach tennis en la región de Hernandarias, Paraguay. Organizamos torneos competitivos que reúnen a jugadores de todos los niveles, desde amateurs hasta profesionales. Nuestra misión es fomentar la pasión por el deporte y crear una comunidad activa y alegre alrededor del beach tennis.',
    
    // Tournaments
    'tournaments.title': 'Torneos',
    'tournaments.subtitle': 'Participa en nuestros emocionantes torneos',
    'tournaments.ongoing': 'Torneos en Curso',
    'tournaments.upcoming': 'Próximos Torneos',
    'tournaments.completed': 'Torneos Culminados',
    'tournaments.all': 'Todos',
    'tournaments.amateur': 'Amateur',
    'tournaments.mixed': 'Mixto',
    'tournaments.pro': 'Profesional',
    'tournaments.viewDetails': 'Ver Detalles',
    'tournaments.back': 'Volver a torneos',
    'tournaments.backHome': 'Volver al inicio',
    'tournaments.notFound': 'Torneo no encontrado',
    'tournaments.details.categories': 'Categorías',
    'tournaments.details.prizes': 'Premios',
    'tournaments.tournament1.title': 'Torneo Apertura 2025',
    'tournaments.tournament1.date': '15 de Febrero, 2025',
    'tournaments.tournament2.title': 'Copa Verano 2025',
    'tournaments.tournament2.date': '10 de Marzo, 2025',
    
    // Inscription
    'inscription.title': '¡Sumate al próximo torneo!',
    'inscription.subtitle': 'Completá el formulario con los datos de ambos jugadores',
    'inscription.form.player1': 'Datos del Jugador 1',
    'inscription.form.player2': 'Datos del Jugador 2',
    'inscription.form.firstName': 'Nombre',
    'inscription.form.lastName': 'Apellido',
    'inscription.form.document': 'Número de documento',
    'inscription.form.phone': 'Número de celular',
    'inscription.form.email': 'Email',
    'inscription.form.city': 'Ciudad',
    'inscription.form.category': 'Categoría del torneo',
    'inscription.form.category.amateur': 'Amateur',
    'inscription.form.category.mixed': 'Mixto',
    'inscription.form.category.pro': 'Profesional',
    'inscription.form.terms': 'Acepto los términos y condiciones',
    'inscription.form.termsLink': 'Ver términos y condiciones',
    'inscription.form.submit': 'Enviar inscripción',
    'inscription.form.required': 'Todos los campos son obligatorios',
    'inscription.whatsapp': 'Contactar por WhatsApp',
    
    // Contact
    'contact.title': 'Contacto',
    'contact.info': 'Información de Contacto',
    'contact.location': 'Hernandarias, Paraguay',
    'contact.phone': '+595 981 189 807',
    'contact.email': 'info@bt.com.py',
    'contact.hours': 'Horarios de atención',
    'contact.hours.value': 'Lunes a domingo: 07:00 - 22:00',
    'contact.whatsapp': 'Contactar por WhatsApp',
    
    // Footer
    'footer.quick': 'Enlaces rápidos',
    'footer.social': 'Síguenos',
    'footer.copyright': '© 2025 BT Hernandarias. Todos los derechos reservados.',
  },
  pt: {
    // Header
    'nav.home': 'Início',
    'nav.tournaments': 'Torneios',
    'nav.about': 'Sobre Nós',
    'nav.contact': 'Contato',
    'nav.signup': 'Inscrição',
    'cta.signup': 'Inscrever-me no torneio',
    
    // Hero
    'hero.title': 'Viva a paixão do Beach Tennis em Hernandarias',
    'hero.subtitle': 'Organizamos os melhores torneios de beach tennis.',
    'hero.cta.primary': 'Inscrever-me no torneio',
    'hero.cta.secondary': 'Saber mais',
    
    // Services
    'services.title': 'O que oferecemos',
    'services.tournaments.title': 'Torneios Locais',
    'services.tournaments.desc': 'Organizamos eventos competitivos para jogadores de todos os níveis em Hernandarias.',
    'services.regional.title': 'Torneios Regionais',
    'services.regional.desc': 'Eventos de maior escala que reúnem os melhores jogadores da região.',
    'services.competitive.title': 'Ambiente Competitivo',
    'services.competitive.desc': 'Fomentamos o espírito esportivo e a competição saudável em cada torneio.',
    'services.cta': 'Ver próximos torneios',
    
    // About
    'about.title': 'Sobre BT Hernandarias',
    'about.text': 'BT (Beach Tennis Hernandarias) é uma organização dedicada a impulsionar o crescimento do beach tennis na região de Hernandarias, Paraguai. Organizamos torneios competitivos que reúnem jogadores de todos os níveis, desde amadores até profissionais. Nossa missão é fomentar a paixão pelo esporte e criar uma comunidade ativa e alegre em torno do beach tennis.',
    
    // Tournaments
    'tournaments.title': 'Torneios',
    'tournaments.subtitle': 'Participe dos nossos emocionantes torneios',
    'tournaments.ongoing': 'Torneios em Andamento',
    'tournaments.upcoming': 'Próximos Torneios',
    'tournaments.completed': 'Torneios Finalizados',
    'tournaments.all': 'Todos',
    'tournaments.amateur': 'Amador',
    'tournaments.mixed': 'Misto',
    'tournaments.pro': 'Profissional',
    'tournaments.viewDetails': 'Ver Detalhes',
    'tournaments.back': 'Voltar aos torneios',
    'tournaments.backHome': 'Voltar ao início',
    'tournaments.notFound': 'Torneio não encontrado',
    'tournaments.details.categories': 'Categorias',
    'tournaments.details.prizes': 'Prêmios',
    'tournaments.tournament1.title': 'Torneio Abertura 2025',
    'tournaments.tournament1.date': '15 de Fevereiro, 2025',
    'tournaments.tournament2.title': 'Copa Verão 2025',
    'tournaments.tournament2.date': '10 de Março, 2025',
    
    // Inscription
    'inscription.title': 'Junte-se ao próximo torneio!',
    'inscription.subtitle': 'Preencha o formulário com os dados de ambos os jogadores',
    'inscription.form.player1': 'Dados do Jogador 1',
    'inscription.form.player2': 'Dados do Jogador 2',
    'inscription.form.firstName': 'Nome',
    'inscription.form.lastName': 'Sobrenome',
    'inscription.form.document': 'Número do documento',
    'inscription.form.phone': 'Número do celular',
    'inscription.form.email': 'Email',
    'inscription.form.city': 'Cidade',
    'inscription.form.category': 'Categoria do torneio',
    'inscription.form.category.amateur': 'Amador',
    'inscription.form.category.mixed': 'Misto',
    'inscription.form.category.pro': 'Profissional',
    'inscription.form.terms': 'Aceito os termos e condições',
    'inscription.form.termsLink': 'Ver termos e condições',
    'inscription.form.submit': 'Enviar inscrição',
    'inscription.form.required': 'Todos os campos são obrigatórios',
    'inscription.whatsapp': 'Contatar pelo WhatsApp',
    
    // Contact
    'contact.title': 'Contato',
    'contact.info': 'Informação de Contato',
    'contact.location': 'Hernandarias, Paraguai',
    'contact.phone': '+595 981 189 807',
    'contact.email': 'info@bt.com.py',
    'contact.hours': 'Horários de atendimento',
    'contact.hours.value': 'Segunda a domingo: 07:00 - 22:00',
    'contact.whatsapp': 'Contatar pelo WhatsApp',
    
    // Footer
    'footer.quick': 'Links rápidos',
    'footer.social': 'Siga-nos',
    'footer.copyright': '© 2025 BT Hernandarias. Todos os direitos reservados.',
  },
};

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

export const LanguageProvider = ({ children }: { children: ReactNode }) => {
  const [language, setLanguage] = useState<Language>('es');

  const toggleLanguage = () => {
    setLanguage((prev) => (prev === 'es' ? 'pt' : 'es'));
  };

  const t = (key: string): string => {
    return translations[language][key as keyof typeof translations.es] || key;
  };

  return (
    <LanguageContext.Provider value={{ language, toggleLanguage, t }}>
      {children}
    </LanguageContext.Provider>
  );
};

export const useLanguage = () => {
  const context = useContext(LanguageContext);
  if (!context) {
    throw new Error('useLanguage must be used within a LanguageProvider');
  }
  return context;
};
