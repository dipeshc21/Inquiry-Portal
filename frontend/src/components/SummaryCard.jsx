export default function SummaryCard({
  title,
  value,
  icon: Icon,
  description,
  loading = false,
}) {
  return (
    <div className="panel p-5">
      <div className="flex items-start justify-between gap-3">
        <p className="text-sm font-medium text-slate-500">{title}</p>

        {Icon && (
          <div className="rounded-xl bg-brand-50 p-2.5 text-brand-600">
            <Icon className="h-5 w-5" aria-hidden="true" />
          </div>
        )}
      </div>

      {loading ? (
        <div
          aria-label="Loading statistic"
          className="mt-2 h-9 w-24 animate-pulse rounded bg-slate-100"
        />
      ) : (
        <p className="mt-2 text-3xl font-bold tracking-tight">{value}</p>
      )}

      {description && (
        <p className="mt-2 text-xs text-slate-500">{description}</p>
      )}
    </div>
  );
}
