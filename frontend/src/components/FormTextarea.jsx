import { useId } from "react";

export default function FormTextarea({
  label,
  error,
  help,
  id,
  required,
  rows = 4,
  className = "",
  ...props
}) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const descriptionId = `${inputId}-description`;

  return (
    <div className={className}>
      {label && (
        <label htmlFor={inputId} className="field-label">
          {label}
          {required && (
            <span aria-hidden="true" className="ml-1 text-red-500">
              *
            </span>
          )}
        </label>
      )}

      <textarea
        id={inputId}
        required={required}
        rows={rows}
        aria-invalid={Boolean(error)}
        aria-describedby={error || help ? descriptionId : undefined}
        className={`field-input resize-y ${error ? "border-red-400" : ""}`}
        {...props}
      />

      {(error || help) && (
        <p
          id={descriptionId}
          className={error ? "field-error" : "field-help"}
          role={error ? "alert" : undefined}
        >
          {error || help}
        </p>
      )}
    </div>
  );
}
