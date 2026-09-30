import { forwardRef, useId } from "react";

const FormInput = forwardRef(function FormInput(
  {
    label,
    error,
    help,
    id,
    required,
    className = "",
    ...props
  },
  ref,
) {
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

      <input
        ref={ref}
        id={inputId}
        required={required}
        aria-invalid={Boolean(error)}
        aria-describedby={error || help ? descriptionId : undefined}
        className={`field-input ${error ? "border-red-400" : ""}`}
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
});

export default FormInput;
