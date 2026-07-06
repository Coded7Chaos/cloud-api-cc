export type IconName =
  | "home"
  | "hash"
  | "chat"
  | "video"
  | "settings"
  | "search"
  | "bell"
  | "phone"
  | "camera"
  | "more"
  | "smile"
  | "clip"
  | "send"
  | "pin"
  | "mail"
  | "file"
  | "image";

export type Chat = {
  name: string;
  message: string;
  initials: string;
  status: "online" | "away";
  unread?: number;
  active?: boolean;
};

export type Group = {
  name: string;
  initials: string;
  className: string;
};

export type Message = {
  text: string;
  time: string;
  variant: "received" | "sent";
};

export type SharedFile = {
  name: string;
  size: string;
  type: "pdf" | "image";
};
