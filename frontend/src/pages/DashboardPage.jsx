import { useState } from "react";
import { Link } from "react-router-dom";
import {
  Archive,
  ArrowUpRight,
  Clock,
  Inbox,
  RefreshCw,
  Target,
  TrendingUp,
  Trophy,
} from "lucide-react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import toast from "react-hot-toast";
import dashboardApi from "../api/dashboard";
import inquiriesApi from "../api/inquiries";
import remindersApi from "../api/reminders";
import Button from "../components/Button";
import DataTable from "../components/DataTable";
import DetailPanel from "../components/DetailPanel";
import ErrorState from "../components/ErrorState";
import ReminderItem from "../components/ReminderItem";
import Spinner from "../components/Spinner";
import StatusBadge from "../components/StatusBadge";
import SummaryCard from "../components/SummaryCard";
import useAuth from "../hooks/useAuth";
import useFetch from "../hooks/useFetch";
import { CHART_COLORS, SOURCES, STATUSES } from "../utils/constants";
import { errorMessage } from "../utils/errors";
import {
  formatDate,
  formatNumber,
  labelFor,
} from "../utils/formatters";

function ChartPlaceholder({ loading, empty, children }) {
  if (loading) {
    return (
      <div className="flex h-72 items-center justify-center text-brand-600">
        <Spinner label="Loading chart" />
      </div>
    );
  }

  if (empty) {
    return (
      <div className="flex h-72 items-center justify-center text-sm text-slate-500">
        No inquiries to display yet.
      </div>
    );
  }

  return <div className="h-72 min-w-0">{children}</div>;
}

export default function DashboardPage() {
  const { user } = useAuth();
  const [busyReminder, setBusyReminder] = useState(null);

  const stats = useFetch((signal) => dashboardApi.stats(signal));
  const recent = useFetch((signal) =>
    inquiriesApi.list(
      { per_page: 5, sort_by: "created_at", sort_dir: "desc" },
      signal,
    ),
  );
  const reminders = useFetch((signal) =>
    remindersApi.mine({ per_page: 5 }, signal),
  );

  const data = stats.data;
  const refreshing = stats.loading || recent.loading || reminders.loading;

  const bySource = (data?.by_source ?? []).map((item) => ({
    ...item,
    label: labelFor(SOURCES, item.source),
  }));

  const byStatus = (data?.by_status ?? []).map((item) => ({
    ...item,
    label: labelFor(STATUSES, item.status),
  }));

  const cards = [
    { title: "Total inquiries", value: data?.total, icon: Inbox },
    { title: "Open", value: data?.open, icon: Target },
    { title: "Pending", value: data?.pending, icon: Clock },
    { title: "Closed", value: data?.closed, icon: Archive },
    { title: "Won", value: data?.won, icon: Trophy },
    {
      title: "Conversion rate",
      value: `${Number(data?.conversion_rate ?? 0).toFixed(1)}%`,
      icon: TrendingUp,
      description: "Won inquiries ÷ closed inquiries",
    },
  ];

  const columns = [
    {
      key: "reference_no",
      label: "Reference",
      render: (row) => (
        <Link to={`/inquiries/${row.id}`} className="text-link whitespace-nowrap">
          {row.reference_no}
        </Link>
      ),
    },
    {
      key: "name",
      label: "Customer",
      render: (row) => (
        <div className="min-w-36">
          <p className="font-medium">{row.name}</p>
          <p className="mt-0.5 text-xs text-slate-500">{row.email}</p>
        </div>
      ),
    },
    {
      key: "subject",
      label: "Subject",
      render: (row) => (
        <p className="max-w-64 truncate" title={row.subject}>
          {row.subject}
        </p>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: "created_at",
      label: "Received",
      render: (row) => (
        <span className="whitespace-nowrap text-slate-500">
          {formatDate(row.created_at)}
        </span>
      ),
    },
  ];

  async function refreshAll() {
    const results = await Promise.all([
      stats.refresh(),
      recent.refresh(),
      reminders.refresh(),
    ]);

    if (results.every(Boolean)) {
      toast.success("Dashboard refreshed.");
    } else {
      toast.error("Some dashboard information could not be refreshed.");
    }
  }

  async function completeReminder(reminder) {
    setBusyReminder(reminder.id);

    try {
      await remindersApi.complete(reminder.id);
      toast.success("Reminder completed.");
      await reminders.refresh();
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusyReminder(null);
    }
  }

  return (
    <div className="page-container">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="page-title">
            Welcome back, {user?.name?.split(" ")[0]}
          </h1>
          <p className="page-description">
            {user?.role === "agent"
              ? "An overview of the inquiries assigned to you."
              : "Your customer conversations and follow-ups at a glance."}
          </p>
        </div>

        <Button
          variant="secondary"
          onClick={refreshAll}
          loading={refreshing}
        >
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      </header>

      {stats.error && (
        <ErrorState error={stats.error} onRetry={stats.refresh} />
      )}

      <section
        aria-label="Inquiry summary"
        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6"
      >
        {cards.map((card) => (
          <SummaryCard
            key={card.title}
            {...card}
            value={
              card.title === "Conversion rate"
                ? card.value
                : formatNumber(card.value)
            }
            loading={stats.loading}
          />
        ))}
      </section>

      {data && (
        <p className="text-sm text-slate-500">
          <span className="font-semibold text-slate-700">
            {formatNumber(data.new_today)}
          </span>{" "}
          inquiries received today
          {user?.role === "agent" && (
            <>
              {" · "}
              <span className="font-semibold text-slate-700">
                {formatNumber(data.my_open_assigned)}
              </span>{" "}
              open inquiries assigned to you
            </>
          )}
        </p>
      )}

      <div className="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <DetailPanel
          title="Inquiry volume"
          description="Daily submissions over the last 30 days"
        >
          <ChartPlaceholder
            loading={stats.loading}
            empty={!data?.per_day?.some((day) => day.count > 0)}
          >
            <ResponsiveContainer width="100%" height="100%">
              <LineChart
                data={data?.per_day ?? []}
                margin={{ top: 10, right: 15, left: -20, bottom: 5 }}
              >
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                <XAxis
                  dataKey="date"
                  minTickGap={35}
                  tick={{ fontSize: 11 }}
                  tickFormatter={(value) =>
                    formatDate(`${value}T12:00:00`, {
                      year: undefined,
                      month: "short",
                      day: "numeric",
                    })
                  }
                />
                <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                <Tooltip
                  labelFormatter={(value) =>
                    formatDate(`${value}T12:00:00`)
                  }
                />
                <Line
                  name="Inquiries"
                  type="monotone"
                  dataKey="count"
                  stroke="#4f46e5"
                  strokeWidth={3}
                  dot={false}
                  activeDot={{ r: 5 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </ChartPlaceholder>
        </DetailPanel>

        <DetailPanel title="Lead sources" description="Where inquiries originate">
          <ChartPlaceholder loading={stats.loading} empty={!data?.total}>
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={bySource.filter((item) => item.count > 0)}
                  dataKey="count"
                  nameKey="label"
                  innerRadius={58}
                  outerRadius={88}
                  paddingAngle={3}
                >
                  {bySource
                    .filter((item) => item.count > 0)
                    .map((item) => (
                      <Cell
                        key={item.source}
                        fill={
                          CHART_COLORS[
                            SOURCES.findIndex(
                              (source) => source.value === item.source,
                            )
                          ]
                        }
                      />
                    ))}
                </Pie>
                <Tooltip />
                <Legend wrapperStyle={{ fontSize: "12px" }} />
              </PieChart>
            </ResponsiveContainer>
          </ChartPlaceholder>
        </DetailPanel>
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
        <DetailPanel title="Inquiry status" description="Current pipeline distribution">
          <ChartPlaceholder loading={stats.loading} empty={!data?.total}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart
                data={byStatus}
                margin={{ top: 10, right: 10, left: -20, bottom: 5 }}
              >
                <CartesianGrid
                  strokeDasharray="3 3"
                  stroke="#e2e8f0"
                  vertical={false}
                />
                <XAxis
                  dataKey="label"
                  tick={{ fontSize: 10 }}
                  interval={0}
                />
                <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                <Tooltip cursor={{ fill: "#f8fafc" }} />
                <Bar
                  name="Inquiries"
                  dataKey="count"
                  radius={[6, 6, 0, 0]}
                  maxBarSize={48}
                >
                  {byStatus.map((item, index) => (
                    <Cell key={item.status} fill={CHART_COLORS[index]} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </ChartPlaceholder>
        </DetailPanel>

        <DetailPanel
          title="My upcoming reminders"
          description="Overdue and upcoming follow-ups"
          actions={
            <Link to="/reminders" className="text-link text-xs">
              View all
            </Link>
          }
        >
          {reminders.error ? (
            <ErrorState error={reminders.error} onRetry={reminders.refresh} />
          ) : reminders.loading ? (
            <div className="flex justify-center py-10 text-brand-600">
              <Spinner label="Loading reminders" />
            </div>
          ) : reminders.data?.length ? (
            <div className="space-y-3">
              {reminders.data.map((reminder) => (
                <ReminderItem
                  key={reminder.id}
                  reminder={reminder}
                  showInquiry
                  busy={busyReminder === reminder.id}
                  onComplete={completeReminder}
                />
              ))}
            </div>
          ) : (
            <p className="py-10 text-center text-sm text-slate-500">
              You have no outstanding reminders.
            </p>
          )}
        </DetailPanel>
      </div>

      <section className="panel overflow-hidden">
        <header className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
          <h2 className="font-semibold">Recent inquiries</h2>
          <Link
            to="/inquiries"
            className="text-link inline-flex items-center gap-1 text-sm"
          >
            View all
            <ArrowUpRight className="h-4 w-4" />
          </Link>
        </header>

        {recent.error ? (
          <div className="p-5">
            <ErrorState error={recent.error} onRetry={recent.refresh} />
          </div>
        ) : (
          <DataTable
            columns={columns}
            rows={recent.data ?? []}
            loading={recent.loading}
            emptyTitle="No inquiries yet"
            emptyDescription="New customer inquiries will appear here."
          />
        )}
      </section>
    </div>
  );
}
