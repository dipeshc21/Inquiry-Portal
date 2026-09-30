import { useEffect, useId, useState } from "react";
import { FileText, Upload, X } from "lucide-react";
import { FILE_ACCEPT, MAX_FILES } from "../utils/constants";
import { formatBytes } from "../utils/formatters";

const EMPTY_FILES = [];

export default function FileUpload({
  files = EMPTY_FILES,
  onChange,
  errors = {},
  disabled = false,
  label = "Attachments",
}) {
  const inputId = useId();
  const [previews, setPreviews] = useState([]);

  useEffect(() => {
    const next = files.map((file) =>
      ["image/jpeg", "image/png"].includes(file.type)
        ? URL.createObjectURL(file)
        : null,
    );

    setPreviews(next);

    return () => {
      next.forEach((url) => {
        if (url) URL.revokeObjectURL(url);
      });
    };
  }, [files]);

  function addFiles(event) {
    const selected = Array.from(event.target.files ?? []);
    const next = [...files];

    selected.forEach((file) => {
      const duplicate = next.some(
        (existing) =>
          existing.name === file.name &&
          existing.size === file.size &&
          existing.lastModified === file.lastModified,
      );

      if (!duplicate) next.push(file);
    });

    onChange(next);
    event.target.value = "";
  }

  return (
    <div>
      <span className="field-label">{label}</span>

      <label
        htmlFor={inputId}
        className={[
          "flex flex-col items-center justify-center gap-2 rounded-xl",
          "border-2 border-dashed border-slate-300 bg-slate-50 p-6",
          "text-center text-sm text-slate-600",
          disabled
            ? "cursor-not-allowed opacity-60"
            : "cursor-pointer hover:border-brand-400 hover:bg-brand-50",
        ].join(" ")}
      >
        <Upload className="h-6 w-6 text-brand-500" aria-hidden="true" />
        <span className="font-medium">Choose files to upload</span>
        <span className="text-xs">
          PDF, Word, JPG, or PNG. Up to {MAX_FILES} files, 5 MB each.
        </span>

        <input
          id={inputId}
          type="file"
          multiple
          accept={FILE_ACCEPT}
          disabled={disabled}
          onChange={addFiles}
          className="sr-only"
          aria-describedby={`${inputId}-errors`}
        />
      </label>

      <div id={`${inputId}-errors`} aria-live="polite">
        {errors.files && <p className="field-error">{errors.files}</p>}
      </div>

      {files.length > 0 && (
        <ul className="mt-3 space-y-2">
          {files.map((file, index) => (
            <li
              key={`${file.name}-${file.size}-${file.lastModified}`}
              className="rounded-lg border border-slate-200 bg-white p-3"
            >
              <div className="flex items-center gap-3">
                {previews[index] ? (
                  <img
                    src={previews[index]}
                    alt={`Preview of ${file.name}`}
                    className="h-12 w-12 rounded-md object-cover"
                  />
                ) : (
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-slate-100">
                    <FileText className="h-6 w-6 text-slate-500" />
                  </div>
                )}

                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{file.name}</p>
                  <p className="text-xs text-slate-500">
                    {formatBytes(file.size)}
                  </p>
                </div>

                <button
                  type="button"
                  disabled={disabled}
                  aria-label={`Remove ${file.name}`}
                  onClick={() =>
                    onChange(files.filter((_, item) => item !== index))
                  }
                  className="rounded-md p-2 text-slate-500 hover:bg-red-50 hover:text-red-600"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>

              {errors[`files.${index}`] && (
                <p className="field-error">
                  {errors[`files.${index}`]}
                </p>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
