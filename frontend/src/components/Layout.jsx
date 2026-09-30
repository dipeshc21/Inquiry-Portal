import { useEffect, useRef, useState } from "react";
import {
  Activity,
  Bell,
  ChevronRight,
  ExternalLink,
  Inbox,
  LayoutDashboard,
  LogOut,
  Menu,
  Settings,
  Users,
  UsersRound,
  X,
} from "lucide-react";
import {
  Link,
  NavLink,
  Outlet,
  useLocation,
  useNavigate,
} from "react-router-dom";
import toast from "react-hot-toast";
import useAuth from "../hooks/useAuth";
import { APP_NAME } from "../utils/constants";
import { initials } from "../utils/formatters";
import Button from "./Button";

const navigation = [
  { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { to: "/inquiries", label: "Inquiries", icon: Inbox },
  { to: "/reminders", label: "My reminders", icon: Bell },
  { to: "/users", label: "Users", icon: Users, admin: true },
  { to: "/teams", label: "Teams", icon: UsersRound, admin: true },
  { to: "/activity-logs", label: "Activity log", icon: Activity, admin: true },
  { to: "/settings", label: "Settings", icon: Settings, admin: true },
];

export default function Layout() {
  const { user, isAdmin, logout } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  const menuButtonRef = useRef(null);
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    setMobileOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    if (!mobileOpen) return undefined;

    function closeOnEscape(event) {
      if (event.key === "Escape") {
        setMobileOpen(false);
        menuButtonRef.current?.focus();
      }
    }

    window.addEventListener("keydown", closeOnEscape);

    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [mobileOpen]);

  async function handleLogout() {
    setLoggingOut(true);

    try {
      await logout();
      toast.success("You have been signed out.");
    } catch {
      toast(
        "Signed out on this device. The server could not confirm token revocation.",
      );
    } finally {
      setLoggingOut(false);
      navigate("/login", { replace: true });
    }
  }

  const current = navigation.find(
    (item) =>
      location.pathname === item.to ||
      location.pathname.startsWith(`${item.to}/`),
  );

  return (
    <div className="min-h-screen">
      <a
        href="#main-content"
        className="sr-only z-50 rounded bg-white p-3 focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
      >
        Skip to content
      </a>

      {mobileOpen && (
        <button
          type="button"
          aria-label="Close navigation"
          className="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
          onClick={() => setMobileOpen(false)}
        />
      )}

      <aside
        id="main-navigation"
        aria-label="Main navigation"
        className={[
          "fixed inset-y-0 left-0 z-40 flex w-64 flex-col",
          "border-r border-slate-200 bg-white transition-transform",
          "lg:visible lg:translate-x-0",
          mobileOpen
            ? "visible translate-x-0"
            : "invisible -translate-x-full",
        ].join(" ")}
      >
        <div className="flex h-20 items-center gap-3 px-6">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white">
            <Inbox className="h-6 w-6" aria-hidden="true" />
          </div>

          <Link to="/dashboard" className="min-w-0">
            <span className="block font-bold tracking-tight">
              Inquiry Portal
            </span>
            <span className="text-xs text-slate-500">
              Customer workspace
            </span>
          </Link>

          <button
            type="button"
            aria-label="Close navigation"
            className="ml-auto rounded p-1 text-slate-500 lg:hidden"
            onClick={() => setMobileOpen(false)}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
          {navigation
            .filter((item) => !item.admin || isAdmin)
            .map(({ to, label, icon: Icon }) => (
              <NavLink
                key={to}
                to={to}
                className={({ isActive }) =>
                  [
                    "flex items-center gap-3 rounded-lg px-3 py-3",
                    "text-sm font-medium transition-colors",
                    isActive
                      ? "bg-brand-50 text-brand-700"
                      : "text-slate-600 hover:bg-slate-50",
                  ].join(" ")
                }
              >
                <Icon className="h-5 w-5" aria-hidden="true" />
                {label}
              </NavLink>
            ))}
        </nav>

        <div className="space-y-3 border-t border-slate-200 p-4">
          <Link
            to="/inquiry"
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-50"
          >
            Public inquiry form
            <ExternalLink className="h-4 w-4" />
          </Link>

          <Button
            variant="secondary"
            className="w-full"
            loading={loggingOut}
            onClick={handleLogout}
          >
            <LogOut className="h-4 w-4" />
            Sign out
          </Button>
        </div>
      </aside>

      <div className="lg:pl-64">
        <header className="sticky top-0 z-20 flex h-20 items-center justify-between gap-4 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
          <div className="flex items-center gap-3">
            <button
              ref={menuButtonRef}
              type="button"
              aria-label="Open navigation"
              aria-expanded={mobileOpen}
              aria-controls="main-navigation"
              onClick={() => setMobileOpen(true)}
              className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
            >
              <Menu className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-2 text-sm">
              <span className="hidden text-slate-400 sm:inline">
                Workspace
              </span>
              <ChevronRight className="hidden h-4 w-4 text-slate-300 sm:block" />
              <span className="font-medium">
                {current?.label ?? APP_NAME}
              </span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <Link
              to="/reminders"
              aria-label="View reminders"
              className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
            >
              <Bell className="h-5 w-5" />
            </Link>

            <div className="hidden text-right sm:block">
              <p className="text-sm font-medium">{user?.name}</p>
              <p className="text-xs capitalize text-slate-500">
                {user?.role}
              </p>
            </div>

            <div
              aria-hidden="true"
              className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700"
            >
              {initials(user?.name)}
            </div>
          </div>
        </header>

        <main
          id="main-content"
          tabIndex={-1}
          className="p-4 outline-none sm:p-6 lg:p-8"
        >
          <Outlet />
        </main>
      </div>
    </div>
  );
}
