/**
 * Converts a Hex color code to an RGB string.
 * @param hex The hex color string (e.g., "#ffffff" or "ffffff")
 * @returns String in format "r, g, b" or null if invalid
 */
export const hexToRgb = (hex: string): string | null => {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : null;
};