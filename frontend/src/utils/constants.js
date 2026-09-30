export const APP_NAME =
  import.meta.env.VITE_APP_NAME || "Inquiry Management Portal";

export const STATUSES = [
  { value: "new", label: "New" },
  { value: "contacted", label: "Contacted" },
  { value: "qualified", label: "Qualified" },
  { value: "pending", label: "Pending" },
  { value: "won", label: "Won" },
  { value: "lost", label: "Lost" },
];

export const SOURCES = [
  { value: "website", label: "Website" },
  { value: "campaign", label: "Campaign" },
  { value: "referral", label: "Referral" },
  { value: "social_media", label: "Social media" },
  { value: "email", label: "Email" },
  { value: "other", label: "Other" },
];

export const PRIORITIES = [
  { value: "low", label: "Low" },
  { value: "medium", label: "Medium" },
  { value: "high", label: "High" },
];

export const ROLES = [
  { value: "admin", label: "Administrator" },
  { value: "manager", label: "Manager" },
  { value: "agent", label: "Agent" },
];

export const STATUS_CLASSES = {
  new: "bg-blue-50 text-blue-700 ring-blue-200",
  contacted: "bg-cyan-50 text-cyan-700 ring-cyan-200",
  qualified: "bg-violet-50 text-violet-700 ring-violet-200",
  pending: "bg-amber-50 text-amber-700 ring-amber-200",
  won: "bg-emerald-50 text-emerald-700 ring-emerald-200",
  lost: "bg-rose-50 text-rose-700 ring-rose-200",
};

export const PRIORITY_CLASSES = {
  low: "bg-slate-100 text-slate-600 ring-slate-200",
  medium: "bg-amber-50 text-amber-700 ring-amber-200",
  high: "bg-red-50 text-red-700 ring-red-200",
};

export const CHART_COLORS = [
  "#4f46e5",
  "#06b6d4",
  "#8b5cf6",
  "#f59e0b",
  "#10b981",
  "#f43f5e",
];

export const FILE_ACCEPT = ".pdf,.doc,.docx,.jpg,.jpeg,.png";
export const MAX_FILE_SIZE = 5 * 1024 * 1024;
export const MAX_FILES = 3;

export const FILE_EXTENSIONS = [
  "pdf",
  "doc",
  "docx",
  "jpg",
  "jpeg",
  "png",
];

export const PAGE_SIZES = [15, 30, 50, 100];

export const OPEN_STATUSES = ["new", "contacted", "qualified"];
export const CLOSED_STATUSES = ["won", "lost"];
