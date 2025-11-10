import { useParams, useNavigate } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { GroupStage } from "@/components/GroupStage";
import { KnockoutBracket } from "@/components/KnockoutBracket";
import { LanguageProvider } from "@/hooks/useLanguage";
import Header from "@/components/Header";
import Footer from "@/components/Footer";

// Sample data - replace with real data from your backend
const groupAMatches = [
  { team1: "R. Kerber / T. Lazari", team2: "C. Manys / L. Oyarzabal", score1: "WO", score2: "x" },
  { team1: "A. Primo / T. Gusso", team2: "M. Souza / L. Scalon", score1: 4, score2: 6 },
  { team1: "R. Kerber / T. Lazari", team2: "M. Souza / L. Scalon", score1: 6, score2: 3 },
  { team1: "A. Primo / T. Gusso", team2: "C. Manys / L. Oyarzabal", score1: "WO", score2: "x" },
  { team1: "M. Souza / L. Scalon", team2: "C. Manys / L. Oyarzabal", score1: "WO", score2: "x" },
  { team1: "R. Kerber / T. Lazari", team2: "A. Primo / T. Gusso", score1: 6, score2: 7 },
];

const groupAStandings = [
  { team: "Roberto Kerber\nThiago Lazari", match1: true, match2: true, match3: false, sg: 2 },
  { team: "Artur Primo\nThiago Gusso", match1: false, match2: true, match3: true, sg: -1 },
  { team: "Marlon Souza\nLuan Scalon", match1: true, match2: false, match3: true, sg: -1 },
  { team: "Cássio Manys\nLeonardo Oyarzabal", match1: false, match2: false, match3: false, sg: 0 },
];

const groupBMatches = [
  { team1: "T. Alves / F. Gormanns", team2: "E. Domingues / A. Ferreira", score1: "WO", score2: "x" },
  { team1: "L. Pacholek / E. Guinart", team2: "F. Kuvada / F. Summa", score1: 7, score2: 6 },
  { team1: "T. Alves / F. Gormanns", team2: "F. Kuvada / F. Summa", score1: "WO", score2: "x" },
  { team1: "L. Pacholek / E. Guinart", team2: "E. Domingues / A. Ferreira", score1: 2, score2: 6 },
  { team1: "F. Kuvada / F. Summa", team2: "E. Domingues / A. Ferreira", score1: 6, score2: 1 },
  { team1: "T. Alves / F. Gormanns", team2: "L. Pacholek / E. Guinart", score1: "WO", score2: "x" },
];

const groupBStandings = [
  { team: "Tiago Alves\nFernando Gormanns", match1: false, match2: false, match3: false, sg: 0 },
  { team: "Leonardo Pacholek\nEnrique Guinart", match1: true, match2: false, match3: true, sg: -3 },
  { team: "Fabio Kuvada\nFrancisco Summa", match1: false, match2: true, match3: true, sg: 4 },
  { team: "Eduardo Domingues\nAnderson Ferreira", match1: true, match2: true, match3: false, sg: -1 },
];

const quarterfinals = [
  { team1: "R. Kerber", team2: "T. Lazari", winner: 1 as const },
  { team1: "E. Domingues", team2: "A. Ferreira", winner: undefined },
  { team1: "M. Souza", team2: "L. Scalon", winner: undefined },
  { team1: "F. Kuvada", team2: "F. Summa", winner: 1 as const },
];

const semifinals = [
  { team1: "R. Kerber", team2: "T. Lazari", score: "6/3", winner: 1 as const },
  { team1: "F. Kuvada", team2: "F. Summa", score: "6/3", winner: 1 as const },
];

const final = {
  team1: "R. Kerber\nT. Lazari",
  team2: "F. Kuvada\nF. Summa",
  score: "6/2",
  winner: 1 as const,
};

const TournamentBracketsContent = () => {
  const { id } = useParams();
  const navigate = useNavigate();

  return (
    <div className="min-h-screen flex flex-col bg-background">
      <Header />
      
      <main className="flex-1 container mx-auto px-4 py-8 mt-16">
        <Button
          variant="outline"
          className="mb-6"
          onClick={() => navigate(`/torneo/${id}`)}
        >
          <ArrowLeft className="mr-2 h-4 w-4" />
          Volver a Detalles del Torneo
        </Button>

        <div className="space-y-12">
          <div>
            <h1 className="text-4xl font-bold text-foreground mb-2">Llaves del Torneo</h1>
            <p className="text-muted-foreground">Fase de grupos y clasificación eliminatoria</p>
          </div>

          {/* Groups Section */}
          <section className="space-y-8">
            <div className="flex items-center gap-2">
              <div className="p-2 bg-card border border-border rounded-lg">
                <span className="font-bold text-foreground">📊 Grupos</span>
                <p className="text-xs text-muted-foreground">Tenistas por grupo</p>
              </div>
            </div>

            <GroupStage
              groupName="Grupo A"
              matches={groupAMatches}
              standings={groupAStandings}
            />

            <GroupStage
              groupName="Grupo B"
              matches={groupBMatches}
              standings={groupBStandings}
            />
          </section>

          {/* Knockout Section */}
          <section>
            <KnockoutBracket
              quarterfinals={quarterfinals}
              semifinals={semifinals}
              final={final}
              champion={{
                name: "R. Kerber\nT. Lazari",
                score: "Campeones 2025"
              }}
            />
          </section>
        </div>
      </main>

      <Footer />
    </div>
  );
};

const TournamentBrackets = () => {
  return (
    <LanguageProvider>
      <TournamentBracketsContent />
    </LanguageProvider>
  );
};

export default TournamentBrackets;
