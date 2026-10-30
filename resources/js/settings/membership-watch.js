// Pengaturan grup terbuka saat keanggotaan kita berubah: dikeluarkan → ke daftar grup, selain itu muat ulang.

export const initMembershipWatch = () => {
	const root = document.querySelector('[data-membership-watch]');
	const groupId = Number(root?.getAttribute('data-membership-watch') || 0);
	const authUserId = Number(document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content') || 0);
	if (!window.Echo || !groupId || !authUserId) {
		return;
	}

	window.Echo.private(`group.${groupId}`).listen('.group.membership.changed', (payload) => {
		const targetUserId = Number(payload?.target_user_id || 0);
		if (!targetUserId || targetUserId !== authUserId) {
			return;
		}

		if (['removed', 'left'].includes(String(payload?.action || ''))) {
			window.location.href = root.getAttribute('data-groups-url') || '/groups';
			return;
		}

		window.location.reload();
	});
};
