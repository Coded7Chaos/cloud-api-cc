import type { Chat, Group, IconName, Message, SharedFile } from "@/types/chat";

export const navItems: { icon: IconName; label: string; active?: boolean }[] = [
  { icon: "home", label: "Inicio" },
  { icon: "hash", label: "Canales" },
  { icon: "chat", label: "Chat", active: true },
  { icon: "video", label: "Video" },
  { icon: "settings", label: "Configuracion" },
];

export const chats: Chat[] = [
  {
    name: "Sayali Sontakke",
    message: "Gracias, reviso la propuesta.",
    initials: "SS",
    status: "online",
    unread: 3,
    active: true,
  },
  {
    name: "Rohit Agarwal",
    message: "Podemos moverlo a manana.",
    initials: "RA",
    status: "away",
    unread: 1,
  },
  {
    name: "Dipali Patra",
    message: "El prototipo quedo listo.",
    initials: "DP",
    status: "online",
  },
  {
    name: "Aarav Sharma",
    message: "Te comparto los archivos.",
    initials: "AS",
    status: "online",
    unread: 5,
  },
  {
    name: "Neha Verma",
    message: "Perfecto, nos vemos en meet.",
    initials: "NV",
    status: "away",
  },
  {
    name: "Karan Mehta",
    message: "Actualice el tablero.",
    initials: "KM",
    status: "online",
    unread: 2,
  },
];

export const groups: Group[] = [
  {
    name: "UI/UX Designing",
    initials: "UX",
    className: "bg-blue-100 text-blue-700",
  },
  {
    name: "Web Development",
    initials: "WD",
    className: "bg-emerald-100 text-emerald-700",
  },
  {
    name: "Marketing Team",
    initials: "MT",
    className: "bg-amber-100 text-amber-700",
  },
];

export const messages: Message[] = [
  {
    text: "Hola, puedes revisar la pantalla del panel de soporte?",
    time: "09:24",
    variant: "received",
  },
  {
    text: "Si, estoy comparando la lista de mensajes y el perfil lateral.",
    time: "09:26",
    variant: "received",
  },
  {
    text: "OK",
    time: "09:27",
    variant: "sent",
  },
  {
    text: "Tambien adjunte los iconos y las referencias del flujo.",
    time: "09:31",
    variant: "received",
  },
  {
    text: "Queda claro. Lo dejo preparado para revision hoy.",
    time: "09:35",
    variant: "sent",
  },
];

export const sharedFiles: SharedFile[] = [
  { name: "Brand-guidelines.pdf", size: "2.4 MB", type: "pdf" },
  { name: "chat-wireframe.png", size: "840 KB", type: "image" },
  { name: "user-flow.pdf", size: "1.8 MB", type: "pdf" },
  { name: "profile-cover.png", size: "620 KB", type: "image" },
];
