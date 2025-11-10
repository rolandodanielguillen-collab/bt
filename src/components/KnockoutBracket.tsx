import { Card } from "@/components/ui/card";
import { Trophy } from "lucide-react";

interface BracketMatch {
  team1: string;
  team2: string;
  score?: string;
  winner?: 1 | 2;
}

interface KnockoutBracketProps {
  quarterfinals: BracketMatch[];
  semifinals: BracketMatch[];
  final: BracketMatch;
  champion?: {
    name: string;
    score: string;
  };
}

export const KnockoutBracket = ({ quarterfinals, semifinals, final, champion }: KnockoutBracketProps) => {
  return (
    <div className="space-y-8">
      <div className="flex items-center gap-2 mb-6">
        <div className="p-2 bg-card border border-border rounded-lg">
          <span className="font-bold text-foreground">🏆 Finais</span>
          <p className="text-xs text-muted-foreground">Chave eliminatória</p>
        </div>
      </div>

      <div className="flex flex-col lg:flex-row items-start justify-center gap-8 lg:gap-16">
        {/* Quarterfinals */}
        <div className="flex flex-col gap-4">
          <h3 className="text-lg font-bold text-center text-foreground mb-2">Cuartos de Final</h3>
          <div className="flex flex-col gap-8">
          {quarterfinals.map((match, idx) => (
            <Card
              key={idx}
              className={`p-4 min-w-[200px] border-l-4 transition-all ${
                match.winner === 1
                  ? "border-l-primary bg-primary/5"
                  : match.winner === 2
                  ? "border-l-muted"
                  : "border-l-border"
              }`}
            >
              <div className="space-y-2">
                <div
                  className={`text-sm font-medium ${
                    match.winner === 1 ? "text-primary font-bold" : "text-foreground"
                  }`}
                >
                  {idx + 1}. {match.team1}
                </div>
                <div
                  className={`text-sm font-medium ${
                    match.winner === 2 ? "text-primary font-bold" : "text-foreground"
                  }`}
                >
                  {idx + 2}. {match.team2}
                </div>
              </div>
            </Card>
          ))}
          </div>
        </div>

        {/* Connection Line */}
        <div className="hidden lg:block w-8 h-px bg-border self-center" />

        {/* Semifinals */}
        <div className="flex flex-col gap-4">
          <h3 className="text-lg font-bold text-center text-foreground mb-2">Semifinales</h3>
          <div className="flex flex-col gap-8">
          {semifinals.map((match, idx) => (
            <Card
              key={idx}
              className={`p-4 min-w-[200px] border-l-4 transition-all ${
                match.winner === 1
                  ? "border-l-primary bg-primary/5"
                  : match.winner === 2
                  ? "border-l-muted"
                  : "border-l-border"
              }`}
            >
              <div className="space-y-2">
                <div
                  className={`text-sm font-medium ${
                    match.winner === 1 ? "text-primary font-bold" : "text-foreground"
                  }`}
                >
                  {match.team1}
                </div>
                {match.score && (
                  <div className="text-xs text-center text-muted-foreground font-mono">
                    {match.score}
                  </div>
                )}
                <div
                  className={`text-sm font-medium ${
                    match.winner === 2 ? "text-primary font-bold" : "text-foreground"
                  }`}
                >
                  {match.team2}
                </div>
              </div>
            </Card>
          ))}
          </div>
        </div>

        {/* Connection Line */}
        <div className="hidden lg:block w-8 h-px bg-border self-center" />

        {/* Final and Champion */}
        <div className="flex flex-col gap-4">
          <h3 className="text-lg font-bold text-center text-foreground mb-2">Final</h3>
          <div className="flex flex-col lg:flex-row gap-4 items-stretch">
            <Card
              className={`p-6 min-w-[220px] border-l-4 border-l-primary bg-gradient-to-r from-primary/10 to-transparent`}
            >
              <div className="space-y-3">
                <div
                  className={`text-base font-medium ${
                    final.winner === 1 ? "text-primary font-bold" : "text-foreground"
                  }`}
                >
                  {final.team1}
                </div>
                {final.score && (
                  <div className="text-sm text-center text-muted-foreground font-mono font-bold">
                    {final.score}
                  </div>
                )}
                <div
                  className={`text-base font-medium ${
                    final.winner === 2 ? "text-primary font-bold" : "text-foreground"
                  }`}
                >
                  {final.team2}
                </div>
              </div>
            </Card>

            {/* Champion Card */}
            {champion && (
              <Card className="p-6 min-w-[220px] border-2 border-yellow-500/50 bg-gradient-to-br from-yellow-500/10 via-background to-yellow-500/5 shadow-xl">
                <div className="flex flex-col items-center text-center space-y-3 h-full justify-center">
                  <div className="p-3 bg-yellow-500/20 rounded-full">
                    <Trophy className="w-8 h-8 text-yellow-600 dark:text-yellow-400" />
                  </div>
                  <div>
                    <h3 className="text-xs font-bold text-yellow-700 dark:text-yellow-500 uppercase tracking-wide mb-2">
                      🏆 Campeones
                    </h3>
                    <p className="text-base font-bold text-foreground whitespace-pre-line">
                      {champion.name}
                    </p>
                    {champion.score && (
                      <p className="text-sm text-muted-foreground font-mono mt-1">
                        {champion.score}
                      </p>
                    )}
                  </div>
                </div>
              </Card>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
