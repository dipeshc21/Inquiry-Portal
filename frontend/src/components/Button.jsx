import { forwardRef } from "react";
import Spinner from "./Spinner";

const variants = {
  primary:
    "border-transparent bg-brand-600 text-white hover:bg-brand-700",
  secondary:
    "border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
  danger:
    "border-transparent bg-red-600 text-white hover:bg-red-700",
  ghost:
    "border-transparent bg-transparent text-slate-600 hover:bg-slate-100",
};

const sizes = {
  sm: "min-h-8 px-3 py-1.5 text-xs",
  md: "min-h-10 px-4 py-2 text-sm",
  lg: "min-h-12 px-5 py-3 text-base",
};

const Button = forwardRef(function Button(
  {
    children,
    variant = "primary",
    size = "md",
    loading = false,
    disabled = false,
    type = "button",
    className = "",
    ...props
  },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={[
        "inline-flex items-center justify-center gap-2 rounded-lg",
        "border font-medium transition-colors disabled:opacity-50",
        variants[variant] ?? variants.primary,
        sizes[size] ?? sizes.md,
        className,
      ].join(" ")}
      {...props}
    >
      {loading && <Spinner label="Processing" />}
      {children}
    </button>
  );
});

export default Button;
