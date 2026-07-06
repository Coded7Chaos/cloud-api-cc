export function Avatar({
  initials,
  size = "h-11 w-11",
}: {
  initials: string;
  size?: string;
}) {
  return (
    <div
      className={`${size} flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#0B4C8C] to-[#18A0A8] text-sm font-bold text-white shadow-sm`}
    >
      {initials}
    </div>
  );
}
