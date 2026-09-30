export default function DetailPanel({
  title,
  description,
  actions,
  children,
  className = "",
}) {
  return (
    <section className={`panel ${className}`}>
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
          <h2 className="font-semibold text-slate-900">{title}</h2>
          {description && (
            <p className="mt-1 text-xs text-slate-500">{description}</p>
          )}
        </div>
        {actions}
      </header>

      <div className="p-5">{children}</div>
    </section>
  );
}
