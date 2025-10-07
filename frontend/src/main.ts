interface ApiFilters {
  startDate?: string | null;
  endDate?: string | null;
}

interface Metric {
  label: string;
  value: number;
  formatted: string | number;
}

interface ReportDescriptor {
  id: string;
  name: string;
  description: string;
  metrics: Metric[];
  suggestedFilters: string[];
  url?: string;
}

interface ReportsResponse {
  filters: ApiFilters;
  data: {
    reports: ReportDescriptor[];
  };
}

const API_ENDPOINT = new URL("/backend/api/reports.php", window.location.origin).toString();
const form = document.querySelector<HTMLFormElement>("#filter-form");
const reportsContainer = document.querySelector<HTMLDivElement>("#reports-container");
const emptyState = document.querySelector<HTMLDivElement>("#reports-empty");
const errorState = document.querySelector<HTMLDivElement>("#reports-error");
const lastUpdated = document.querySelector<HTMLParagraphElement>("#last-updated");
const reportTemplate = document.querySelector<HTMLTemplateElement>("#report-card-template");

let currentRequest: AbortController | null = null;

function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === "AbortError";
}

function toQueryString(filters: ApiFilters): string {
  const params = new URLSearchParams();

  if (filters.startDate) params.set("startDate", filters.startDate);
  if (filters.endDate) params.set("endDate", filters.endDate);

  const query = params.toString();
  return query.length > 0 ? `?${query}` : "";
}

function readFiltersFromForm(formElement: HTMLFormElement): ApiFilters {
  const formData = new FormData(formElement);
  const filters: ApiFilters = {};

  const startDate = formData.get("startDate");
  const endDate = formData.get("endDate");

  if (typeof startDate === "string" && startDate) {
    filters.startDate = startDate;
  }

  if (typeof endDate === "string" && endDate) {
    filters.endDate = endDate;
  }

  return filters;
}

async function loadReports(filters: ApiFilters = {}): Promise<void> {
  if (!reportsContainer || !emptyState || !errorState) {
    throw new Error("Unable to initialise report page");
  }

  if (currentRequest) {
    currentRequest.abort();
  }

  currentRequest = new AbortController();
  const signal = currentRequest.signal;

  const queryString = toQueryString(filters);
  const endpoint = `${API_ENDPOINT}${queryString}`;

  reportsContainer.innerHTML = "";
  reportsContainer.dataset.loading = "true";
  emptyState.hidden = true;
  errorState.hidden = true;
  errorState.textContent = "";

  try {
    const response = await fetch(endpoint, { signal });

    if (!response.ok) {
      throw new Error(`Request failed with status ${response.status}`);
    }

    const payload = (await response.json()) as ReportsResponse;

    renderReports(payload.data.reports);
    updateFilters(payload.filters);
    updateTimestamp();
  } catch (error) {
    if (isAbortError(error)) {
      return;
    }

    errorState.textContent = error instanceof Error ? error.message : "Unexpected error while loading reports.";
    errorState.hidden = false;
    if (reportsContainer) {
      reportsContainer.innerHTML = "";
    }
  } finally {
    reportsContainer.removeAttribute("data-loading");
    if (reportsContainer.childElementCount === 0 && errorState.hidden) {
      emptyState.hidden = false;
    }
  }
}

function renderReports(reports: ReportDescriptor[]): void {
  if (!reportsContainer || !reportTemplate) {
    return;
  }

  reportsContainer.innerHTML = "";

  if (reports.length === 0) {
    emptyState && (emptyState.hidden = false);
    return;
  }

  const fragment = document.createDocumentFragment();

  reports.forEach((report) => {
    const instance = reportTemplate.content.cloneNode(true) as DocumentFragment;
    const card = instance.querySelector<HTMLAnchorElement>(".report-card");
    const title = instance.querySelector<HTMLHeadingElement>(".report-title");
    const description = instance.querySelector<HTMLParagraphElement>(".report-description");

    if (card) card.href = buildReportLink(report);
    if (title) title.textContent = report.name;
    if (description) description.textContent = report.description;

    fragment.append(instance);
  });

  reportsContainer.append(fragment);
}

function buildReportLink(report: ReportDescriptor): string {
  if (report.url) {
    return report.url;
  }

  return `#report-${encodeURIComponent(report.id)}`;
}

function updateFilters(filters: ApiFilters): void {
  if (!form) return;

  const start = form.querySelector<HTMLInputElement>("#start-date");
  const end = form.querySelector<HTMLInputElement>("#end-date");

  if (start) start.value = filters.startDate ?? "";
  if (end) end.value = filters.endDate ?? "";
}

function updateTimestamp(): void {
  if (!lastUpdated) return;
  const now = new Date();
  lastUpdated.textContent = `Last updated: ${now.toLocaleString()}`;
}

if (form) {
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const filters = readFiltersFromForm(form);
    loadReports(filters).catch((error) => {
      console.error("Failed to load reports", error);
    });
  });

  form.addEventListener("reset", () => {
    setTimeout(() => {
      loadReports({}).catch((error) => {
        console.error("Failed to load reports", error);
      });
    }, 0);
  });
}

loadReports({}).catch((error) => {
  console.error("Failed to initialise reports", error);
  if (errorState) {
    errorState.textContent = error instanceof Error ? error.message : String(error);
    errorState.hidden = false;
  }
});
