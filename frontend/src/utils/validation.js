import {
  FILE_EXTENSIONS,
  MAX_FILE_SIZE,
  MAX_FILES,
  SOURCES,
} from "./constants";

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_PATTERN = /^\+?[0-9\s().-]{7,25}$/;

export function validateFiles(files = []) {
  const errors = {};

  if (files.length > MAX_FILES) {
    errors.files = `Select at most ${MAX_FILES} attachments.`;
  }

  files.forEach((file, index) => {
    const extension = file.name.split(".").pop()?.toLowerCase();

    if (!FILE_EXTENSIONS.includes(extension)) {
      errors[`files.${index}`] =
        "Use PDF, DOC, DOCX, JPG, JPEG, or PNG files.";
    } else if (file.size > MAX_FILE_SIZE) {
      errors[`files.${index}`] = "Each attachment must be 5 MB or smaller.";
    } else if (file.size === 0) {
      errors[`files.${index}`] = "Empty files cannot be uploaded.";
    }
  });

  return errors;
}

export function validatePublicInquiry(values, files = []) {
  const errors = validateFiles(files);

  const requiredText = (field, label, maximum, minimum = 1) => {
    const value = String(values[field] ?? "").trim();

    if (!value) {
      errors[field] = `${label} is required.`;
    } else if (value.length < minimum) {
      errors[field] = `${label} must contain at least ${minimum} characters.`;
    } else if (value.length > maximum) {
      errors[field] = `${label} must not exceed ${maximum} characters.`;
    }
  };

  requiredText("name", "Name", 100);
  requiredText("subject", "Subject", 150);
  requiredText("message", "Message", 5000, 10);

  const email = String(values.email ?? "").trim();

  if (!EMAIL_PATTERN.test(email) || email.length > 255) {
    errors.email = "Enter a valid email address.";
  }

  const phone = String(values.phone ?? "").trim();

  if (phone && !PHONE_PATTERN.test(phone)) {
    errors.phone = "Enter a valid phone number.";
  }

  if (String(values.company ?? "").length > 150) {
    errors.company = "Company must not exceed 150 characters.";
  }

  if (
    values.source &&
    !SOURCES.some((source) => source.value === values.source)
  ) {
    errors.source = "Select a valid inquiry source.";
  }

  ["utm_source", "utm_medium", "utm_campaign"].forEach((field) => {
    if (String(values[field] ?? "").length > 150) {
      errors[field] = "Tracking values must not exceed 150 characters.";
    }
  });

  if (values.honeypot) {
    errors.honeypot = "The submission could not be accepted.";
  }

  return errors;
}

export function validateReminder(values) {
  const errors = {};
  const title = String(values.title ?? "").trim();
  const date = new Date(values.remind_at);

  if (!title) {
    errors.title = "Enter a reminder title.";
  } else if (title.length > 200) {
    errors.title = "The title must not exceed 200 characters.";
  }

  if (
    !values.remind_at ||
    Number.isNaN(date.getTime()) ||
    date.getTime() <= Date.now()
  ) {
    errors.remind_at = "Choose a reminder time in the future.";
  }

  return errors;
}

export function validatePassword(password) {
  return password.length >= 12
    && password.length <= 200
    && /[a-z]/.test(password)
    && /[A-Z]/.test(password)
    && /\d/.test(password);
}
