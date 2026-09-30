import { useEffect, useId, useRef } from "react";
import { createPortal } from "react-dom";
import { X } from "lucide-react";

let openModalCount = 0;
let originalOverflow = "";
const modalStack = [];

export default function Modal({
  open,
  onClose,
  title,
  children,
  footer,
  busy = false,
  size = "md",
}) {
  const titleId = useId();
  const panelRef = useRef(null);
  const closeRef = useRef(onClose);
  const busyRef = useRef(busy);

  closeRef.current = onClose;
  busyRef.current = busy;

  useEffect(() => {
    if (!open) return undefined;

    const previouslyFocused = document.activeElement;
    const panel = panelRef.current;
    const modalToken = Symbol("modal");

    modalStack.push(modalToken);

    if (openModalCount === 0) {
      originalOverflow = document.body.style.overflow;
      document.body.style.overflow = "hidden";
    }

    openModalCount += 1;

    const focusableSelector = [
      "button:not([disabled])",
      "a[href]",
      "input:not([disabled])",
      "select:not([disabled])",
      "textarea:not([disabled])",
      '[tabindex]:not([tabindex="-1"])',
    ].join(",");

    function focusableElements() {
      return [...panel.querySelectorAll(focusableSelector)].filter(
        (element) => element.getClientRects().length > 0,
      );
    }

    const frame = requestAnimationFrame(() => {
      (focusableElements()[0] ?? panel).focus();
    });

    function handleKey(event) {
      if (modalStack.at(-1) !== modalToken) return;

      if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();

        if (!busyRef.current) closeRef.current();
      }

      if (event.key !== "Tab") return;

      const elements = focusableElements();
      const first = elements[0];
      const last = elements.at(-1);

      if (!first) {
        event.preventDefault();
        panel.focus();
        return;
      }

      if (
        event.shiftKey &&
        (document.activeElement === first ||
          !panel.contains(document.activeElement))
      ) {
        event.preventDefault();
        last.focus();
      } else if (
        !event.shiftKey &&
        (document.activeElement === last ||
          !panel.contains(document.activeElement))
      ) {
        event.preventDefault();
        first.focus();
      }
    }

    document.addEventListener("keydown", handleKey);

    return () => {
      cancelAnimationFrame(frame);
      document.removeEventListener("keydown", handleKey);

      const index = modalStack.indexOf(modalToken);
      if (index !== -1) modalStack.splice(index, 1);

      openModalCount -= 1;

      if (openModalCount === 0) {
        document.body.style.overflow = originalOverflow;
      }

      if (previouslyFocused instanceof HTMLElement) {
        previouslyFocused.focus();
      }
    };
  }, [open]);

  if (!open) return null;

  const width = {
    sm: "max-w-md",
    md: "max-w-xl",
    lg: "max-w-3xl",
  }[size] ?? "max-w-xl";

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) onClose();
      }}
    >
      <section
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        className={`my-auto flex max-h-[90dvh] w-full flex-col rounded-2xl bg-white shadow-xl ${width}`}
      >
        <header className="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
          <h2 id={titleId} className="text-lg font-semibold">
            {title}
          </h2>

          <button
            type="button"
            disabled={busy}
            onClick={onClose}
            aria-label="Close dialog"
            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
          >
            <X className="h-5 w-5" />
          </button>
        </header>

        <div className="overflow-y-auto p-6">{children}</div>

        {footer && (
          <footer className="flex flex-wrap justify-end gap-3 border-t border-slate-200 px-6 py-4">
            {footer}
          </footer>
        )}
      </section>
    </div>,
    document.body,
  );
}
