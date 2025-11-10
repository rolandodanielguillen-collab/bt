import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FileText, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface RulesModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export const RulesModal = ({ open, onOpenChange }: RulesModalProps) => {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center justify-between">
            <DialogTitle className="text-2xl font-bold flex items-center gap-2">
              <FileText className="h-6 w-6 text-primary" />
              Reglamento Beach Tennis
            </DialogTitle>
            <Button 
              variant="outline" 
              size="sm"
              onClick={() => window.open('/reglamento-exa-cntp-2024-bt.pdf', '_blank')}
            >
              <Download className="h-4 w-4 mr-2" />
              Descargar PDF
            </Button>
          </div>
        </DialogHeader>
        
        <div className="prose prose-sm max-w-none dark:prose-invert">
          <div className="space-y-6 text-foreground">
            <section>
              <h2 className="text-xl font-bold text-primary mb-3">1. DECLARACIONES INICIALES</h2>
              <p className="text-muted-foreground mb-3">
                Todos los competidores participantes del TORNEO EXA TACURU PUCÙ BEACH TENNIS, al inscribirse en el evento, 
                automáticamente aceptan que conocen y están de acuerdo con el reglamento del torneo.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-primary mb-3">2. FINES Y OBJETIVOS</h2>
              <p className="text-muted-foreground mb-2">
                Proyectar y desarrollar un juego cordial y competitivo dentro de un ambiente fraterno de cuidado y respeto 
                por todos los participantes.
              </p>
              <ul className="list-disc pl-6 space-y-1 text-muted-foreground">
                <li>Promover la integración social y deportiva entre los practicantes del BEACH TENNIS</li>
                <li>Estimular el desenvolvimiento técnico deportivo</li>
                <li>Interactuar con los demás segmentos con el fin de contribuir en el desenvolvimiento del deporte</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-bold text-primary mb-3">3. SISTEMA DE DISPUTA</h2>
              <h3 className="text-lg font-semibold mb-2">3.1. De la Composición de las Llaves</h3>
              <p className="text-muted-foreground mb-3">
                Los grupos serán compuestos de acuerdo a la cantidad de duplas inscriptas:
              </p>
              <div className="bg-muted/30 rounded-lg p-4 space-y-2 text-sm">
                <p>• 3 a 5 duplas: todos contra todos (Round Robin)</p>
                <p>• 6 duplas: 2 grupos de 3 duplas</p>
                <p>• 7 duplas: 2 grupos (1 grupo de 3 duplas y 1 grupo de 4 duplas)</p>
                <p>• 8 duplas: 2 grupos de 4 duplas</p>
                <p>• 9 duplas: 3 grupos de 3 duplas</p>
                <p>• 10 duplas: 3 grupos (2 grupos de 3 duplas y 1 grupo de 4 duplas)</p>
                <p>• 11 duplas: 3 grupos (1 grupo de 3 duplas y 2 grupos de 4 duplas)</p>
                <p>• 12 duplas: 4 grupos de 3 duplas</p>
              </div>
            </section>

            <section>
              <h2 className="text-xl font-bold text-primary mb-3">4. CRITERIOS DE DESEMPATE</h2>
              <p className="text-muted-foreground mb-3">
                En caso de empate en la puntuación de los grupos realizados, la definición de las posiciones será 
                determinada por los siguientes criterios:
              </p>
              <div className="space-y-3">
                <div>
                  <h4 className="font-semibold mb-2">Empate con 2 duplas/atletas:</h4>
                  <ul className="list-disc pl-6 text-muted-foreground">
                    <li>Enfrentamiento directo</li>
                  </ul>
                </div>
                <div>
                  <h4 className="font-semibold mb-2">Empate con 3 duplas/atletas:</h4>
                  <ul className="list-disc pl-6 text-muted-foreground">
                    <li>Mayor número de victorias</li>
                    <li>Mayor saldo de games</li>
                    <li>Prevaleciendo el empate se realizará un sorteo</li>
                  </ul>
                </div>
              </div>
            </section>

            <section>
              <h2 className="text-xl font-bold text-primary mb-3">5. FORMATO DE JUEGO</h2>
              <div className="space-y-3">
                <div className="bg-muted/30 rounded-lg p-4">
                  <h4 className="font-semibold mb-2">Fase de Grupos, Eliminatorias y Semifinales:</h4>
                  <p className="text-muted-foreground">
                    Un set de hasta 6 games, habiendo empate en 6-6 tie-break de 7 puntos.
                  </p>
                </div>
                <div className="bg-muted/30 rounded-lg p-4">
                  <h4 className="font-semibold mb-2">Finales y Tercer Puesto:</h4>
                  <p className="text-muted-foreground">
                    Mejor de 3 sets. Los dos primeros sets hasta 6 games, en caso de igualdad se juega el tercer set 
                    un tie break a 10 puntos.
                  </p>
                </div>
              </div>
            </section>

            <section>
              <h2 className="text-xl font-bold text-primary mb-3">6. NORMAS GENERALES</h2>
              <ul className="list-disc pl-6 space-y-2 text-muted-foreground">
                <li>El material que el atleta estará utilizando es de su responsabilidad</li>
                <li>Los participantes deberán presentarse 15 minutos antes del inicio de su partido para confirmar presencia</li>
                <li>Habrá una tolerancia de 5 minutos para el inicio del primer juego</li>
                <li>El calentamiento es de 5 minutos</li>
                <li>El tiempo de cambio de lado no podrá pasar 1 minuto y 30 segundos</li>
                <li>No es permitido recibir instrucciones en cancha durante el partido</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-bold text-primary mb-3">7. CÓDIGO DE CONDUCTA</h2>
              <p className="text-muted-foreground mb-3">
                El código de conducta será aplicado por el árbitro general. Las infracciones incluyen:
              </p>
              <ul className="list-disc pl-6 space-y-1 text-muted-foreground">
                <li>Obscenidad audible o visible</li>
                <li>Abuso de pelotas, raqueta o equipamiento</li>
                <li>Abuso verbal o físico</li>
                <li>Conducta antideportiva</li>
              </ul>
              <div className="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4 mt-3">
                <h4 className="font-semibold mb-2 text-amber-600 dark:text-amber-400">Penalidades:</h4>
                <ul className="space-y-1 text-sm">
                  <li>1ª Ofensa: Advertencia</li>
                  <li>2ª Ofensa: Pérdida de punto</li>
                  <li>3ª Ofensa: Pérdida de game</li>
                  <li>4ª Ofensa: Descalificación (a criterio del árbitro)</li>
                </ul>
              </div>
            </section>

            <section>
              <h2 className="text-xl font-bold text-primary mb-3">8. ATENCIÓN MÉDICA</h2>
              <p className="text-muted-foreground">
                La atención médica no podrá exceder el tiempo reglamentario de 5 minutos. A cada atleta se permite 
                apenas un tiempo médico durante el torneo.
              </p>
            </section>

            <section className="bg-primary/5 rounded-lg p-4 border border-primary/20">
              <h2 className="text-xl font-bold text-primary mb-3">9. FAIR PLAY</h2>
              <p className="text-muted-foreground">
                Esperamos que todos los atletas respeten el espíritu de fair play y no violencia en todos los momentos, 
                aceptando las condiciones de entrada al evento y los términos del código de conducta.
              </p>
            </section>

            <p className="text-sm text-muted-foreground italic mt-6">
              Los casos omitidos en este reglamento serán analizados por la comisión organizadora del evento.
            </p>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
};
