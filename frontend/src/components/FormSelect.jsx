import { useId } from "react";

export default function FormSelect({
  label,
  options = [],
  placeholder,
  error,
  help,
  id,
  required,
  className = "",
  children,
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

      <select
        id={inputId}
        required={required}
        aria-invalid={Boolean(error)}
        aria-describedby={error || help ? descriptionId : undefined}
        className={`field-input ${error ? "border-red-400" : ""}`}
        {...props}
      >
        {placeholder !== undefined && (
          <option value="">{placeholder}</option>
        )}

        {options.map((option) => (
          <option
            key={option.value}
            value={option.value}
            disabled={option.disabled}
          >
            {option.label}
          </option>
        ))}

        {children}
      </select>

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
