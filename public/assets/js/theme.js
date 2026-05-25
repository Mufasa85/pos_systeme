/**
 * Theme System for POS System
 * Complete theme management with localStorage persistence
 */

const themes = {
  blue: {
    "--primary": "#0B5E88",
    "--primary-dark": "#003B5C",
    "--accent": "#2AB7E6",
    "--background": "#E6F4FA",
    "--card": "#FFFFFF",
    "--foreground": "#1a1a2e",
    "--muted": "#64748b",
    "--border": "#e2e8f0",
    "--sidebar-bg": "#003B5C",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#0B5E88",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  green: {
    "--primary": "#16A34A",
    "--primary-dark": "#166534",
    "--accent": "#4ADE80",
    "--background": "#ECFDF5",
    "--card": "#FFFFFF",
    "--foreground": "#166534",
    "--muted": "#64748b",
    "--border": "#a7f3d0",
    "--sidebar-bg": "#14532D",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#16A34A",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  purple: {
    "--primary": "#7C3AED",
    "--primary-dark": "#5B21B6",
    "--accent": "#A78BFA",
    "--background": "#EDE9FE",
    "--card": "#FFFFFF",
    "--foreground": "#5B21B6",
    "--muted": "#64748b",
    "--border": "#c4b5fd",
    "--sidebar-bg": "#4C1D95",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#7C3AED",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  yellow: {
    "--primary": "#EAB308",
    "--primary-dark": "#CA8A04",
    "--accent": "#FACC15",
    "--background": "#FEFCE8",
    "--card": "#FFFFFF",
    "--foreground": "#854D0e",
    "--muted": "#64748b",
    "--border": "#fde047",
    "--sidebar-bg": "#A16207",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#EAB308",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  orange: {
    "--primary": "#F97316",
    "--primary-dark": "#C2410C",
    "--accent": "#FB923C",
    "--background": "#FFF7ED",
    "--card": "#FFFFFF",
    "--foreground": "#9A3412",
    "--muted": "#64748b",
    "--border": "#fed7aa",
    "--sidebar-bg": "#9A3412",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#F97316",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  gray: {
    "--primary": "#64748B",
    "--primary-dark": "#334155",
    "--accent": "#94A3B8",
    "--background": "#F1F5F9",
    "--card": "#FFFFFF",
    "--foreground": "#334155",
    "--muted": "#64748b",
    "--border": "#cbd5e1",
    "--sidebar-bg": "#1E293B",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#475569",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  red: {
    "--primary": "#DC2626",
    "--primary-dark": "#991B1B",
    "--accent": "#F87171",
    "--background": "#FEF2F2",
    "--card": "#FFFFFF",
    "--foreground": "#991B1B",
    "--muted": "#64748b",
    "--border": "#fecaca",
    "--sidebar-bg": "#7F1D1D",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#DC2626",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  black: {
    "--primary": "#111827",
    "--primary-dark": "#000000",
    "--accent": "#374151",
    "--background": "#0F172A",
    "--card": "#1E293B",
    "--foreground": "#F8FAFC",
    "--muted": "#94A3B8",
    "--border": "#334155",
    "--sidebar-bg": "#020617",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#111827",
    "--success": "#22C55E",
    "--warning": "#F59E0B",
    "--danger": "#EF4444"
  },

  cyan: {
    "--primary": "#06B6D4",
    "--primary-dark": "#0891B2",
    "--accent": "#22D3EE",
    "--background": "#F0F9FA",
    "--card": "#FFFFFF",
    "--foreground": "#164E63",
    "--muted": "#64748b",
    "--border": "#cffafe",
    "--sidebar-bg": "#164E63",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#06B6D4",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  indigo: {
    "--primary": "#4F46E5",
    "--primary-dark": "#4338CA",
    "--accent": "#818CF8",
    "--background": "#EEF2FF",
    "--card": "#FFFFFF",
    "--foreground": "#1E1B4B",
    "--muted": "#64748b",
    "--border": "#c7d2fe",
    "--sidebar-bg": "#312E81",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#4F46E5",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  emerald: {
    "--primary": "#10B981",
    "--primary-dark": "#059669",
    "--accent": "#34D399",
    "--background": "#ECFDF5",
    "--card": "#FFFFFF",
    "--foreground": "#064E3B",
    "--muted": "#64748b",
    "--border": "#a7f3d0",
    "--sidebar-bg": "#064E3B",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#10B981",
    "--success": "#059669",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

  gold: {
    "--primary": "#EAB308",
    "--primary-dark": "#CA8A04",
    "--accent": "#FACC15",
    "--background": "#FEFCE8",
    "--card": "#FFFFFF",
    "--foreground": "#422006",
    "--muted": "#64748b",
    "--border": "#fef08a",
    "--sidebar-bg": "#422006",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#EAB308",
    "--success": "#22C55E",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  },

midnight: {
    "--primary": "#0A2540",
    "--primary-dark": "#020B16",
    "--accent": "#032357",
    "--background": "#050B14",
    "--card": "#0F172A",
    "--foreground": "#F8FAFC",
    "--muted": "#94A3B8",
    "--border": "#1E293B",
    "--sidebar-bg": "#030712",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#112240",
    "--success": "#10B981",
    "--warning": "#F59E0B",
    "--danger": "#EF4444",
},

  ice: {
    "--primary": "#7DD3FC",
    "--primary-dark": "#38BDF8",
    "--accent": "#BAE6FD",
    "--background": "#F0F9FF",
    "--card": "#FFFFFF",
    "--foreground": "#0C4A6E",
    "--muted": "#64748b",
    "--border": "#e0f2fe",
    "--sidebar-bg": "#0369A1",
    "--sidebar-text": "#FFFFFF",
    "--sidebar-hover": "#7DD3FC",
    "--success": "#10b981",
    "--warning": "#f59e0b",
    "--danger": "#ef4444"
  }
};

/**
 * Apply a theme by name
 * @param {string} themeName - Name of the theme to apply
 */
function applyTheme(themeName) {
  const theme = themes[themeName];
  if (!theme) {
    console.warn(`Theme '${themeName}' not found, applying default 'blue'`);
    return applyTheme('blue');
  }

  const root = document.documentElement;
  
  // Apply CSS variables with smooth transition
  root.style.transition = 'background-color 0.3s ease, color 0.3s ease';
  
  Object.entries(theme).forEach(([property, value]) => {
    root.style.setProperty(property, value);
  });

  // Remove transition after application
  setTimeout(() => {
    root.style.transition = '';
  }, 300);

  // Update active button visual state
  updateThemeButtons(themeName);
  
  // Save to localStorage
  saveTheme(themeName);
}

/**
 * Load theme from server (for all users to have same theme)
 */
async function loadThemeFromServer() {
  try {
    const response = await fetch('/api/settings/theme');
    const data = await response.json();
    return data.theme || 'blue';
  } catch (e) {
    console.warn('Could not load theme from server, using localStorage');
    return localStorage.getItem('theme') || 'blue';
  }
}

/**
 * Save theme to server (Admin only)
 */
async function saveThemeToServer(themeName) {
  try {
    const formData = new FormData();
    formData.append('theme', themeName);
    
    const response = await fetch('/api/settings/theme', {
      method: 'POST',
      body: formData
    });
    return response.ok;
  } catch (e) {
    console.warn('Could not save theme to server');
    return false;
  }
}

/**
 * Load theme - first from server, fallback to localStorage
 */
async function loadTheme() {
  const savedTheme = await loadThemeFromServer();
  applyTheme(savedTheme);
  return savedTheme;
}

/**
 * Save theme to localStorage AND server
 */
function saveTheme(themeName) {
  localStorage.setItem('theme', themeName);
  // Save to server if admin
  saveThemeToServer(themeName);
}

/**
 * Get current theme name
 * @returns {string} Current theme name
 */
function getCurrentTheme() {
  return localStorage.getItem('theme') || 'blue';
}

/**
 * Update theme button visual states
 * @param {string} activeTheme - Currently active theme name
 */
function updateThemeButtons(activeTheme) {
  document.querySelectorAll('.theme-btn').forEach(btn => {
    if (btn.dataset.theme === activeTheme) {
      btn.classList.add('active');
      btn.style.transform = 'scale(1.1)';
      btn.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
    } else {
      btn.classList.remove('active');
      btn.style.transform = 'scale(1)';
      btn.style.boxShadow = '';
    }
  });
}

// Expose functions globally
window.applyTheme = applyTheme;
window.loadTheme = loadTheme;
window.saveTheme = saveTheme;
window.getCurrentTheme = getCurrentTheme;

// Auto-load theme when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loadTheme);
} else {
  loadTheme();
}