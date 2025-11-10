import { LanguageProvider } from '@/hooks/useLanguage';
import Header from '@/components/Header';
import Hero from '@/components/Hero';
import Services from '@/components/Services';
import About from '@/components/About';
import Tournaments from '@/components/Tournaments';
import Contact from '@/components/Contact';
import Footer from '@/components/Footer';

const Index = () => {
  return (
    <LanguageProvider>
      <div className="min-h-screen">
        <Header />
        <Hero />
        <Services />
        <About />
        <Tournaments />
        <Contact />
        <Footer />
      </div>
    </LanguageProvider>
  );
};

export default Index;
