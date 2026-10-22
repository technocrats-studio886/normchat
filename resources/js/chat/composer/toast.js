// Notifikasi singkat di atas composer.

export const showActionToast = (message) => {
	if (!message) {
		return;
	}

	const toast = document.createElement('div');
	toast.className = 'fixed left-1/2 bottom-24 z-[75] -translate-x-1/2 rounded-full bg-slate-900/95 px-3 py-1.5 text-xs font-medium text-white shadow-lg';
	toast.textContent = message;
	document.body.appendChild(toast);
	window.setTimeout(() => {
		if (toast.isConnected) {
			toast.remove();
		}
	}, 1800);
};
