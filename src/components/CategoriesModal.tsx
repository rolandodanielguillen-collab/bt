import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Card, CardContent } from '@/components/ui/card';
import { Users, ArrowRight } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

interface Category {
  name: string;
  description: string;
}

interface CategoriesModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  categories: Category[];
  tournamentId?: string;
}

export const CategoriesModal = ({ open, onOpenChange, categories, tournamentId }: CategoriesModalProps) => {
  const navigate = useNavigate();

  const handleCategoryClick = () => {
    if (tournamentId) {
      navigate(`/torneo/${tournamentId}/llaves`);
      onOpenChange(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="text-2xl font-bold flex items-center gap-2">
            <Users className="h-6 w-6 text-primary" />
            Categorías del Torneo
          </DialogTitle>
        </DialogHeader>
        
        <div className="grid gap-4 py-4">
          {categories.map((category, index) => (
            <Card 
              key={index}
              className="hover:shadow-lg hover:border-primary/50 transition-all cursor-pointer group"
              onClick={handleCategoryClick}
            >
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-xl font-bold mb-2 text-foreground group-hover:text-primary transition-colors">
                      {category.name}
                    </h3>
                    <p className="text-muted-foreground">
                      {category.description}
                    </p>
                  </div>
                  <ArrowRight className="h-6 w-6 text-muted-foreground group-hover:text-primary group-hover:translate-x-1 transition-all" />
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="text-sm text-muted-foreground text-center pb-2">
          Haz clic en una categoría para ver las llaves del torneo
        </div>
      </DialogContent>
    </Dialog>
  );
};
