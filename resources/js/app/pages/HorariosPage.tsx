import { CalendarClock, LayoutGrid } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../components/ui/tabs';
import ScheduleEditor from './horarios/ScheduleEditor';
import ScheduleMatrix from './horarios/ScheduleMatrix';

/**
 * Horarios (solo administradores). Dos vistas de lo mismo:
 *   - Por agente: el editor semanal de turnos.
 *   - Distribución: la planilla horario × día armada desde esos turnos.
 */
export default function HorariosPage() {
    return (
        <Tabs defaultValue="editor" className="h-full flex flex-col gap-3">
            <TabsList className="bg-white/60">
                <TabsTrigger value="editor" className="gap-1.5">
                    <CalendarClock size={15} /> Por agente
                </TabsTrigger>
                <TabsTrigger value="matriz" className="gap-1.5">
                    <LayoutGrid size={15} /> Distribución
                </TabsTrigger>
            </TabsList>
            {/* min-h-0 deja que cada vista maneje su propio scroll. */}
            <TabsContent value="editor" className="min-h-0">
                <ScheduleEditor />
            </TabsContent>
            <TabsContent value="matriz" className="min-h-0">
                <ScheduleMatrix />
            </TabsContent>
        </Tabs>
    );
}
