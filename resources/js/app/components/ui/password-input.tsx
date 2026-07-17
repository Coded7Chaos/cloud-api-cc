import * as React from "react";
import { Eye, EyeOff } from "lucide-react";

import { Input } from "./input";
import { cn } from "./utils";

type PasswordInputProps = Omit<React.ComponentProps<"input">, "type"> & {
  wrapperClassName?: string;
  leftIcon?: React.ReactNode;
};

function PasswordInput({ className, wrapperClassName, leftIcon, disabled, ...props }: PasswordInputProps) {
  const [visible, setVisible] = React.useState(false);
  const label = visible ? "Ocultar contraseña" : "Mostrar contraseña";
  const Icon = visible ? Eye : EyeOff;

  return (
    <div className={cn("relative", wrapperClassName)}>
      {leftIcon && (
        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none">
          {leftIcon}
        </span>
      )}
      <Input
        {...props}
        disabled={disabled}
        type={visible ? "text" : "password"}
        className={cn(leftIcon ? "pl-9" : undefined, "pr-10", className)}
      />
      <button
        type="button"
        onClick={() => setVisible((current) => !current)}
        disabled={disabled}
        aria-label={label}
        title={label}
        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-[#004479] disabled:opacity-50"
      >
        <Icon size={16} />
      </button>
    </div>
  );
}

export { PasswordInput };
