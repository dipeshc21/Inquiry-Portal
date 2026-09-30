import { Suspense } from "react";
import { Toaster } from "react-hot-toast";
import AppRoutes from "./routes";
import Spinner from "./components/Spinner";

export default function App() {
  return (
    <>
      <Suspense
        fallback={
          <div className="flex min-h-screen items-center justify-center text-brand-600">
            <Spinner label="Loading page" />
          </div>
        }
      >
        <AppRoutes />
      </Suspense>

      <Toaster
        position="top-right"
        toastOptions={{
          duration: 4500,
          style: {
            borderRadius: "12px",
            fontSize: "14px",
            maxWidth: "420px",
          },
          success: {
            iconTheme: {
              primary: "#059669",
              secondary: "#ffffff",
            },
          },
        }}
      />
    </>
  );
}
