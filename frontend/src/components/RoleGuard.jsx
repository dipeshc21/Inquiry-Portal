import useAuth from "../hooks/useAuth";

export default function RoleGuard({
  roles,
  children,
  fallback = null,
}) {
  const { user } = useAuth();

  return user && roles.includes(user.role) ? children : fallback;
}
