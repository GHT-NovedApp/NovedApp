const STORAGE_KEY = "novedapp-static-reports-v1";
const SESSION_KEY = "novedapp-static-profile-v1";

const profiles = {
  reportante: { label: "Reportante · EAI", name: "Reportante EAI Manuelita", actor: "EAI" },
  gestor: { label: "Gestor · Operaciones", name: "Gestor de operaciones", actor: "GHT" },
  torre: { label: "Torre de control", name: "Torre de control GHT", actor: "GHT" },
  admin: { label: "Administrador", name: "Administrador de demostración", actor: "GHT" },
};

const initialReports = [
  { id: "NOV-001", type: "CAJA_FALTANTE", actor: "EAI", organization: "Finca Manuelita", reporter: "Reportante EAI", status: "resuelta", createdAt: "2026-09-01T08:23:00", description: "Se reportan 3 cajas faltantes en el despacho EC-2345. El transportador no reportó novedad al momento del recibo.", messages: [{ author: "Reportante EAI", at: "2026-09-01T08:23:00", text: "Se reportan 3 cajas faltantes en el despacho EC-2345." }, { author: "Gestor OSL", at: "2026-09-01T10:05:00", text: "Cajas localizadas en bodega de tránsito. Novedad resuelta." }] },
  { id: "NOV-002", type: "ARRIBO_DEMORA", actor: "CEDI", organization: "BG-Tocancipá", reporter: "Reportante CEDI", status: "en_gestion", createdAt: "2026-09-03T11:15:00", description: "Vehículo con demora de 2 horas por tráfico en vía Bogotá-Tocancipá.", messages: [{ author: "Reportante CEDI", at: "2026-09-03T11:15:00", text: "Se solicita confirmar la hora estimada de llegada." }, { author: "Gestor OSL", at: "2026-09-03T11:40:00", text: "Contactado el conductor. ETA estimado 13:30." }] },
  { id: "NOV-003", type: "EPP_AUSENTE", actor: "EAI", organization: "Finca Manuelita", reporter: "Reportante EAI", status: "abierta", createdAt: "2026-09-05T07:30:00", description: "Operario en área de postcosecha sin guantes ni tapabocas. Se solicitó suspensión inmediata de actividad.", messages: [{ author: "Reportante EAI", at: "2026-09-05T07:30:00", text: "Se detectó operario de empresa contratista trabajando sin EPP básico." }] },
  { id: "NOV-004", type: "AVION_FALLA_MECANICA", actor: "AdC", organization: "EXP BOG", reporter: "Reportante AdC", status: "resuelta", createdAt: "2026-09-02T14:00:00", description: "Aeronave reporta falla hidráulica. Carga debe ser reprogramada.", messages: [{ author: "Reportante AdC", at: "2026-09-02T14:00:00", text: "Vuelo cancelado por falla mecánica. Se solicita activar el protocolo." }, { author: "Gestor OCA", at: "2026-09-02T18:20:00", text: "Carga reubicada y vuelo alterno programado. Novedad resuelta." }] },
  { id: "NOV-005", type: "CARTON_DANADO", actor: "EAI", organization: "Finca Manuelita", reporter: "Reportante EAI", status: "en_gestion", createdAt: "2026-09-04T09:00:00", description: "12 cartones con daño por humedad identificados al momento del descargue.", messages: [{ author: "Reportante EAI", at: "2026-09-04T09:00:00", text: "Se reportan 12 cartones con daño por humedad detectados al descargue." }] },
  { id: "NOV-006", type: "CONDUCTOR_NO_COINCIDE", actor: "CEDI", organization: "BG-FD", reporter: "Reportante CEDI", status: "abierta", createdAt: "2026-09-06T06:45:00", description: "El conductor presentado no coincide con el registrado en el manifiesto de carga.", messages: [{ author: "Reportante CEDI", at: "2026-09-06T06:45:00", text: "El vehículo está retenido en portería mientras se valida el manifiesto." }] },
  { id: "NOV-007", type: "BANDA_FALLA", actor: "OCA", organization: "OCA Bogotá", reporter: "Gestor OCA", status: "reporte", createdAt: "2026-09-04T16:20:00", description: "Banda transportadora línea 3 presenta falla intermitente. Mantenimiento programado.", messages: [{ author: "Gestor OCA", at: "2026-09-04T16:20:00", text: "Se registra novedad de seguimiento para la banda transportadora." }] },
];

const elements = {
  login: document.querySelector("#login-view"),
  app: document.querySelector("#app-view"),
  profileName: document.querySelector("#current-profile"),
  rows: document.querySelector("#report-rows"),
  empty: document.querySelector("#empty-state"),
  count: document.querySelector("#visible-count"),
  search: document.querySelector("#search-input"),
  status: document.querySelector("#status-filter"),
  reportDialog: document.querySelector("#report-dialog"),
  detailDialog: document.querySelector("#detail-dialog"),
  detail: document.querySelector("#detail-content"),
};

let activeProfile = null;
let activeStatus = "todos";
let activeReportId = null;

function safeRead(key, fallback) {
  try {
    const value = localStorage.getItem(key);
    return value ? JSON.parse(value) : fallback;
  } catch {
    return fallback;
  }
}

function saveReports(reports) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(reports));
}

function getReports() {
  const saved = safeRead(STORAGE_KEY, null);
  if (Array.isArray(saved)) return saved;
  const fresh = structuredClone(initialReports);
  saveReports(fresh);
  return fresh;
}

function escapeHtml(value = "") {
  return String(value).replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
}

function formatDate(value) {
  return new Intl.DateTimeFormat("es-CO", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(value));
}

function statusLabel(status) {
  return ({ reporte: "Reporte", abierta: "Abierta", en_gestion: "En gestión", resuelta: "Resuelta" })[status] ?? status;
}

function visibleReports() {
  const query = elements.search.value.trim().toLocaleLowerCase("es");
  return getReports().filter((report) => {
    const canSee = activeProfile !== "reportante" || report.actor === profiles[activeProfile].actor;
    const matchesStatus = activeStatus === "todos" || report.status === activeStatus;
    const matchesQuery = !query || [report.id, report.type, report.organization, report.reporter, report.description].some((value) => String(value).toLocaleLowerCase("es").includes(query));
    return canSee && matchesStatus && matchesQuery;
  }).sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

function render() {
  const reports = getReports();
  const profileReports = reports.filter((report) => activeProfile !== "reportante" || report.actor === profiles[activeProfile].actor);
  const filtered = visibleReports();
  const counts = {
    todos: profileReports.length,
    abierta: profileReports.filter((item) => item.status === "abierta").length,
    en_gestion: profileReports.filter((item) => item.status === "en_gestion").length,
    resuelta: profileReports.filter((item) => item.status === "resuelta").length,
  };
  document.querySelector("#count-all").textContent = counts.todos;
  document.querySelector("#count-open").textContent = counts.abierta;
  document.querySelector("#count-progress").textContent = counts.en_gestion;
  document.querySelector("#count-done").textContent = counts.resuelta;
  elements.count.textContent = `${filtered.length} ${filtered.length === 1 ? "resultado" : "resultados"}`;
  elements.rows.innerHTML = filtered.map((report) => `
    <tr>
      <td><span class="report-id">${escapeHtml(report.id)}</span><span class="report-date">${escapeHtml(formatDate(report.createdAt))}</span></td>
      <td><span class="report-type">${escapeHtml(report.type)}</span><span class="report-description">${escapeHtml(report.description)}</span></td>
      <td>${escapeHtml(report.organization)}</td>
      <td>${escapeHtml(report.reporter)}</td>
      <td><span class="state-badge state--${escapeHtml(report.status)}">${escapeHtml(statusLabel(report.status))}</span></td>
      <td><button class="open-report" type="button" data-open="${escapeHtml(report.id)}" aria-label="Abrir ${escapeHtml(report.id)}">↗</button></td>
    </tr>`).join("");
  elements.empty.hidden = filtered.length !== 0;
  document.querySelectorAll(".metric").forEach((metric) => metric.classList.toggle("is-selected", metric.dataset.status === activeStatus));
}

function enterApp(profile) {
  if (!profiles[profile]) return;
  activeProfile = profile;
  sessionStorage.setItem(SESSION_KEY, profile);
  elements.login.hidden = true;
  elements.app.hidden = false;
  elements.profileName.textContent = profiles[profile].label;
  render();
}

function showLogin() {
  activeProfile = null;
  sessionStorage.removeItem(SESSION_KEY);
  elements.app.hidden = true;
  elements.login.hidden = false;
}

function openDetail(id) {
  const report = getReports().find((item) => item.id === id);
  if (!report) return;
  activeReportId = id;
  const isOperator = ["gestor", "torre", "admin"].includes(activeProfile);
  const messages = report.messages.map((message) => `
    <article class="thread-item"><div class="thread-head"><strong>${escapeHtml(message.author)}</strong><time>${escapeHtml(formatDate(message.at))}</time></div><p>${escapeHtml(message.text)}</p></article>`).join("");
  elements.detail.innerHTML = `
    <div class="detail-wrap">
      <div class="detail-top"><div><p class="eyebrow">${escapeHtml(report.id)} · ${escapeHtml(report.actor)}</p><h2>${escapeHtml(report.type)}</h2><p class="detail-meta">${escapeHtml(report.organization)} · ${escapeHtml(formatDate(report.createdAt))}</p></div><button class="icon-button" type="button" data-close="detail-dialog" aria-label="Cerrar">×</button></div>
      <p class="detail-description">${escapeHtml(report.description)}</p>
      <div class="thread" aria-label="Conversación">${messages}</div>
      <form id="reply-form" class="reply-form"><label class="sr-only" for="reply-text">Escribe una respuesta</label><input id="reply-text" name="message" required maxlength="500" placeholder="Escribe una respuesta..."><button class="button button--primary" type="submit">Responder</button></form>
      <div class="detail-bottom">${isOperator ? `<label>Estado<select id="detail-status"><option value="reporte" ${report.status === "reporte" ? "selected" : ""}>Reporte</option><option value="abierta" ${report.status === "abierta" ? "selected" : ""}>Abierta</option><option value="en_gestion" ${report.status === "en_gestion" ? "selected" : ""}>En gestión</option><option value="resuelta" ${report.status === "resuelta" ? "selected" : ""}>Resuelta</option></select></label>` : "<span class=\"muted-copy\">Vista de seguimiento</span>"}<button class="button button--outline detail-close" type="button" data-close="detail-dialog">Cerrar</button></div>
    </div>`;
  elements.detailDialog.showModal();
}

document.querySelector("#login-form").addEventListener("submit", (event) => {
  event.preventDefault();
  enterApp(document.querySelector("#profile-select").value);
});

document.querySelector("#switch-profile").addEventListener("click", showLogin);
document.querySelector("#new-report").addEventListener("click", () => elements.reportDialog.showModal());
document.querySelector("#export-csv").addEventListener("click", () => {
  const reports = visibleReports();
  const rows = [["ID", "Tipo", "Organización", "Actor", "Reportante", "Estado", "Fecha", "Descripción"], ...reports.map((item) => [item.id, item.type, item.organization, item.actor, item.reporter, statusLabel(item.status), item.createdAt, item.description])];
  const csv = rows.map((row) => row.map((field) => `"${String(field).replaceAll('"', '""')}"`).join(",")).join("\r\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob(["\ufeff", csv], { type: "text/csv;charset=utf-8" }));
  link.download = "novedades.csv";
  link.click();
  URL.revokeObjectURL(link.href);
});

document.querySelectorAll(".metric").forEach((metric) => metric.addEventListener("click", () => {
  activeStatus = metric.dataset.status;
  elements.status.value = activeStatus;
  render();
}));
elements.search.addEventListener("input", render);
elements.status.addEventListener("change", () => { activeStatus = elements.status.value; render(); });
elements.rows.addEventListener("click", (event) => {
  const button = event.target.closest("[data-open]");
  if (button) openDetail(button.dataset.open);
});

document.querySelector("#report-form").addEventListener("submit", (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  const reports = getReports();
  const sequence = String(Date.now()).slice(-6);
  reports.push({
    id: `NOV-${sequence}`,
    type: String(data.get("type")).trim().toLocaleUpperCase("es"),
    actor: String(data.get("actor")),
    organization: String(data.get("organization")).trim(),
    reporter: profiles[activeProfile].name,
    status: "abierta",
    createdAt: new Date().toISOString(),
    description: String(data.get("description")).trim(),
    messages: [{ author: profiles[activeProfile].name, at: new Date().toISOString(), text: String(data.get("description")).trim() }],
  });
  saveReports(reports);
  form.reset();
  elements.reportDialog.close();
  activeStatus = "todos";
  elements.status.value = "todos";
  render();
});

elements.detail.addEventListener("submit", (event) => {
  if (event.target.id !== "reply-form") return;
  event.preventDefault();
  const reports = getReports();
  const report = reports.find((item) => item.id === activeReportId);
  if (!report) return;
  report.messages.push({ author: profiles[activeProfile].name, at: new Date().toISOString(), text: new FormData(event.target).get("message").trim() });
  saveReports(reports);
  openDetail(activeReportId);
  render();
});

elements.detail.addEventListener("change", (event) => {
  if (event.target.id !== "detail-status") return;
  const reports = getReports();
  const report = reports.find((item) => item.id === activeReportId);
  if (report) {
    report.status = event.target.value;
    saveReports(reports);
    openDetail(activeReportId);
    render();
  }
});

document.addEventListener("click", (event) => {
  const closeButton = event.target.closest("[data-close]");
  if (closeButton) document.getElementById(closeButton.dataset.close).close();
});

document.querySelector("#reset-demo").addEventListener("click", () => {
  if (!window.confirm("Se eliminarán los cambios guardados en este navegador. ¿Restablecer la demo?")) return;
  localStorage.removeItem(STORAGE_KEY);
  render();
});

const rememberedProfile = sessionStorage.getItem(SESSION_KEY);
if (rememberedProfile && profiles[rememberedProfile]) enterApp(rememberedProfile);
else getReports();