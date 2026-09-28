/** El QR de Rodante puede ser el token pelado o la URL pública `/qr/{token}`. */
export function normalizeScan(raw: string): string {
  const trimmed = raw.trim();
  if (!trimmed) return '';
  try {
    const url = new URL(trimmed);
    const parts = url.pathname.split('/').filter(Boolean);
    const qr = parts.lastIndexOf('qr');
    if (qr >= 0 && parts[qr + 1]) return decodeURIComponent(parts[qr + 1]);
  } catch {
    // No es una URL: se busca el texto tal cual (número, patente o token).
  }
  return trimmed;
}
