export function errorMessage(error) {
  if (!error) return "An unexpected error occurred.";

  if (error.code === "ERR_CANCELED") return "The request was cancelled.";

  if (error.response?.status === 401) {
    return "Your session has expired or the sign-in details are invalid.";
  }

  if (error.response?.status === 403) {
    return "You do not have permission to perform this action.";
  }

  if (error.response?.status === 409) {
    return "This change conflicts with existing records. Accounts with "
      + "notes or reminders must be deactivated instead of deleted. "
      + "You cannot remove your own administrator access.";
  }

  if (error.response?.data?.message) {
    return error.response.data.message;
  }

  if (error.code === "ECONNABORTED") {
    return "The request timed out. Please try again.";
  }

  if (!error.response) {
    return "Unable to reach the server. Check your connection and try again.";
  }

  return "The request could not be completed.";
}

export function fieldErrors(error) {
  const errors = error?.response?.data?.errors;

  if (!errors || typeof errors !== "object") return {};

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : String(messages),
    ]),
  );
}
