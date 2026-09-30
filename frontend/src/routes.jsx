import { lazy } from "react";
import { Navigate, Route, Routes } from "react-router-dom";
import Layout from "./components/Layout";
import ProtectedRoute from "./components/ProtectedRoute";

const PublicInquiryPage = lazy(() => import("./pages/PublicInquiryPage"));
const LoginPage = lazy(() => import("./pages/LoginPage"));
const DashboardPage = lazy(() => import("./pages/DashboardPage"));
const InquiriesPage = lazy(() => import("./pages/InquiriesPage"));
const InquiryDetailPage = lazy(() => import("./pages/InquiryDetailPage"));
const RemindersPage = lazy(() => import("./pages/RemindersPage"));
const UsersPage = lazy(() => import("./pages/UsersPage"));
const TeamsPage = lazy(() => import("./pages/TeamsPage"));
const ActivityLogsPage = lazy(() => import("./pages/ActivityLogsPage"));
const SettingsPage = lazy(() => import("./pages/SettingsPage"));
const ForbiddenPage = lazy(() => import("./pages/ForbiddenPage"));
const NotFoundPage = lazy(() => import("./pages/NotFoundPage"));

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="/inquiry" element={<PublicInquiryPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/403" element={<ForbiddenPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<Layout />}>
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/inquiries" element={<InquiriesPage />} />
          <Route path="/inquiries/:id" element={<InquiryDetailPage />} />
          <Route path="/reminders" element={<RemindersPage />} />

          <Route element={<ProtectedRoute roles={["admin"]} />}>
            <Route path="/users" element={<UsersPage />} />
            <Route path="/teams" element={<TeamsPage />} />
            <Route path="/activity-logs" element={<ActivityLogsPage />} />
            <Route path="/settings" element={<SettingsPage />} />
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
