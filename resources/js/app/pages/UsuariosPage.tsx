import { Users, ShieldCheck } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../components/ui/tabs';
import UsersPanel from './usuarios/UsersPanel';
import RolesPanel from './usuarios/RolesPanel';

/**
 * Pantalla de administración de accesos (solo administradores, protegida en
 * App.tsx y en el backend con los permisos usuarios.* / roles.*). Divide en dos
 * pestañas los agentes y los roles que definen qué puede hacer cada uno.
 */
export default function UsuariosPage() {
    return (
        <Tabs defaultValue="usuarios" className="h-full flex flex-col gap-3">
            <TabsList className="bg-white/60">
                <TabsTrigger value="usuarios" className="gap-1.5">
                    <Users size={15} /> Usuarios
                </TabsTrigger>
                <TabsTrigger value="roles" className="gap-1.5">
                    <ShieldCheck size={15} /> Roles
                </TabsTrigger>
            </TabsList>
            {/* min-h-0 deja que cada panel maneje su propio scroll sin empujar el layout. */}
            <TabsContent value="usuarios" className="min-h-0">
                <UsersPanel />
            </TabsContent>
            <TabsContent value="roles" className="min-h-0">
                <RolesPanel />
            </TabsContent>
        </Tabs>
    );
}
