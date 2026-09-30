import { AlertCircle } from "lucide-react";
import { errorMessage } from "../utils/errors";
import Button from "./Button";

export default function ErrorState({ error, onRetry }) {
  return (
    <div
      role="alert"
      className="rounded-xl border border-red-200 bg-red-50 p-5"
    >
      <div className="flex items-start gap-3">
        <AlertCircle className="h-5 w-5 shrink-0 text-red-500" />

        <div className="flex-1">
          <p className="text-sm text-red-800">{errorMessage(error)}</p>

          {onRetry && (
            <Button
              variant="secondary"
              size="sm"
              className="mt-3"
              onClick={onRetry}
            >
              Try again
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
