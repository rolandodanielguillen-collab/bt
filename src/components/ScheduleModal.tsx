import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Clock } from 'lucide-react';

interface ScheduleModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export const ScheduleModal = ({ open, onOpenChange }: ScheduleModalProps) => {
  const schedules = [
    { time: '14:00', court1: 'GRUPO 1 - R1- GEN.A MASC.', court2: 'GRUPO 1 - R1 - GEN. B MASC.', court5: 'GRUPO 1 - R1 - GEN. C MASC.' },
    { time: '14:25', court1: 'GRUPO 1-R1 - GEN. A FEM.', court2: 'GRUPO 1 - R1 - GEN. B FEM.', court5: 'GRUPO 1 - R1 - GEN.C FEM.' },
    { time: '14:50', court1: 'GRUPO 2 - R1- GEN.A MASC.', court2: 'GRUPO 2 - R1 - GEN. B MASC.', court5: 'GRUPO 2 - R1 - GEN. C MASC.' },
    { time: '15:15', court1: 'GRUPO 2 - R1 - GEN. A FEM.', court2: 'GRUPO 1 - R2 - GEN. B FEM.', court5: 'GRUPO 2 - R1 - GEN.C FEM.' },
    { time: '15:40', court1: 'GRUPO 1 - R2- GEN.A MASC.', court2: 'GRUPO 1 - R2 - GEN. B MASC.', court5: 'GRUPO 3 - R1 - GEN. C MASC.' },
    { time: '16:05', court1: 'GRUPO 1 - R2 - GEN. A FEM.', court2: 'GRUPO 2 - R1 - GEN. B FEM.', court5: 'GRUPO 3 - R1 - GEN.C FEM.' },
    { time: '16:30', court1: 'GRUPO 2 - R2- GEN.A MASC.', court2: 'GRUPO 2 - R2 - GEN. B MASC.', court5: 'GRUPO 1 - R2 - GEN. C MASC.' },
    { time: '16:55', court1: 'GRUPO 2 - R2 - GEN. A FEM.', court2: 'GRUPO 2 - R2 - GEN. B FEM.', court5: 'GRUPO 1 - R2 - GEN.C FEM.' },
    { time: '17:20', court1: 'GRUPO 3 - R1- GEN.A MASC.', court2: 'GRUPO 3 - R1 - GEN. B MASC.', court5: 'GRUPO 2 - R2 - GEN. C MASC.' },
    { time: '17:45', court1: 'GRUPO 2 - R3 - GEN. A FEM.', court2: 'GRUPO 1 - R3 - GEN. B FEM.', court5: 'GRUPO 2 - R2 - GEN.C FEM.' },
    { time: '18:10', court1: 'GRUPO 3 - R2- GEN.A MASC.', court2: 'GRUPO 1 - R3 - GEN. B MASC.', court5: 'GRUPO 3 - R2 - GEN. C MASC.' },
    { time: '18:35', court1: 'GRUPO 1 - R3 - GEN. A FEM.', court2: 'GRUPO 1 - R4 - GEN. B FEM.', court5: 'GRUPO 3 - R2 - GEN.C FEM.' },
    { time: '19:00', court1: 'GRUPO 1 - R3 - GEN.A MASC.', court2: 'GRUPO 3 - R2 - GEN. B MASC.', court5: 'GRUPO 1 - R3 - GEN. C MASC.' },
    { time: '19:25', court1: 'GRUPO 2 - R4 - GEN. A FEM.', court2: 'GRUPO 2 - R3 - GEN. B FEM.', court5: 'GRUPO 1 - R3 - GEN.C FEM.' },
    { time: '19:50', court1: 'GRUPO 2 - R3- GEN.A MASC.', court2: 'GRUPO 2 - R3 - GEN. B MASC.', court5: 'GRUPO 2 - R3 - GEN. C MASC.' },
    { time: '20:15', court1: 'GRUPO 2 - R5 - GEN. A FEM.', court2: 'GRUPO 2 - R4 - GEN. B FEM.', court5: 'GRUPO 2 - R3 - GEN.C FEM.' },
    { time: '20:40', court1: 'GRUPO 3 - R2- GEN.A MASC.', court2: 'GRUPO 3 - R3 - GEN. B MASC.', court5: 'GRUPO 3 - R3 - GEN. C MASC.' },
    { time: '21:05', court1: 'GRUPO 2 - R6 - GEN. A FEM.', court2: 'GRUPO 1 - R5 - GEN. B FEM.', court5: 'GRUPO 3 - R3 - GEN.C FEM.' },
    { time: '21:30', court1: '', court2: 'GRUPO 2 - R5 - GEN. B FEM.', court5: '' },
    { time: '21:55', court1: '', court2: 'GRUPO 1 - R6 - GEN. B FEM.', court5: '' },
    { time: '22:20', court1: '', court2: 'GRUPO 2 - R6 - GEN. B FEM.', court5: '' },
  ];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-6xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="text-2xl font-bold flex items-center gap-2">
            <Clock className="h-6 w-6 text-primary" />
            Cronograma del Torneo - Sábado
          </DialogTitle>
        </DialogHeader>
        
        <div className="overflow-x-auto">
          <table className="w-full border-collapse">
            <thead>
              <tr className="bg-primary/10">
                <th className="border border-border p-3 text-left font-bold">Horario</th>
                <th className="border border-border p-3 text-left font-bold">Cancha 1</th>
                <th className="border border-border p-3 text-left font-bold">Cancha 2</th>
                <th className="border border-border p-3 text-left font-bold">Cancha 5</th>
              </tr>
            </thead>
            <tbody>
              {schedules.map((schedule, index) => (
                <tr 
                  key={index}
                  className="hover:bg-muted/50 transition-colors"
                >
                  <td className="border border-border p-3 font-semibold text-primary">
                    {schedule.time}
                  </td>
                  <td className="border border-border p-3 text-sm">
                    {schedule.court1}
                  </td>
                  <td className="border border-border p-3 text-sm">
                    {schedule.court2}
                  </td>
                  <td className="border border-border p-3 text-sm">
                    {schedule.court5}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="text-sm text-muted-foreground mt-4 space-y-2">
          <p><strong>Nota:</strong> Los horarios pueden variar según el desarrollo del torneo.</p>
          <p><strong>Duración estimada por partido:</strong> 25 minutos</p>
        </div>
      </DialogContent>
    </Dialog>
  );
};
