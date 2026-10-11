(function() {
	'use strict';
	const editor = document.getElementById('onyx-poll-editor');
	if (editor) {
		const body = editor.querySelector('tbody');
		const rows = () => Array.from(body.querySelectorAll('[data-onyx-row]'));
		const refresh = () => {
			let total = 0;
			rows().forEach((row, index) => {
				row.querySelector('[data-onyx-number]').textContent = index + 1;
				row.querySelectorAll('[name]').forEach((input) => {
					input.name = input.name.replace(/\[answers\]\[[^\]]+\]/, '[answers][' + index + ']');
				});
				row.querySelector('[data-onyx-remove]').disabled = rows().length <= 2;
				row.querySelector('[data-onyx-up]').disabled = index === 0;
				row.querySelector('[data-onyx-down]').disabled = index === rows().length - 1;
				const hasImage = Number(row.querySelector('[data-onyx-image-id]').value) > 0;
				row.querySelector('.onyx-image-control').classList.toggle('has-image', hasImage);
				const imageLabel = hasImage ? onyxPollAdmin.imageChange : onyxPollAdmin.imageChoose;
				row.querySelector('[data-onyx-image]').setAttribute('aria-label', imageLabel);
				row.querySelector('[data-onyx-image]').title = imageLabel;
				total += Number(row.querySelector('[data-onyx-votes]').value) || 0;
			});
			editor.querySelector('#onyx-total').value = total;
			editor.classList.toggle('has-images', editor.querySelector('[name="onyx_poll[images]"]').checked);
		};
		editor.addEventListener('input', refresh);
		editor.addEventListener('change', refresh);
		editor.addEventListener('click', (event) => {
			const button = event.target.closest('button');
			if (! button) {
				return;
			}
			const row = button.closest('[data-onyx-row]');
			if (button.hasAttribute('data-onyx-add')) {
				body.appendChild(document.getElementById('onyx-answer-template').content.cloneNode(true));
			} else if (button.hasAttribute('data-onyx-remove') && rows().length > 2) {
				row.remove();
			} else if (button.hasAttribute('data-onyx-up') && row.previousElementSibling) {
				body.insertBefore(row, row.previousElementSibling);
			} else if (button.hasAttribute('data-onyx-down') && row.nextElementSibling) {
				body.insertBefore(row.nextElementSibling, row);
			} else if (button.hasAttribute('data-onyx-remove-image')) {
				row.querySelector('[data-onyx-image-id]').value = '0';
				row.querySelector('[data-onyx-image-preview]').replaceChildren();
			} else if (button.hasAttribute('data-onyx-image')) {
				const frame = wp.media({ title: onyxPollAdmin.imageTitle, button: { text: onyxPollAdmin.imageButton }, library: { type: 'image' }, multiple: false });
				frame.on('open', () => {
					const imageId = Number(row.querySelector('[data-onyx-image-id]').value);
					if (imageId) {
						const attachment = wp.media.attachment(imageId);
						attachment.fetch();
						frame.state().get('selection').add(attachment);
					}
				});
				frame.on('select', () => {
					const attachment = frame.state().get('selection').first().toJSON();
					row.querySelector('[data-onyx-image-id]').value = attachment.id;
					const image = document.createElement('img');
					image.src = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
					image.alt = attachment.alt || '';
					row.querySelector('[data-onyx-image-preview]').replaceChildren(image);
					refresh();
				});
				frame.open();
			}
			refresh();
		});
		if (window.jQuery && jQuery.fn.sortable) {
			jQuery(body).sortable({
				items: '> tr[data-onyx-row]', handle: '.onyx-drag-handle', axis: 'y', distance: 4,
				tolerance: 'pointer', placeholder: 'onyx-sort-placeholder', forcePlaceholderSize: true,
				helper(event, row) {
					// A detached table row recalculates its columns. Lock a clone to the
					// rendered cell sizes while leaving the original responsive row intact.
					const clone = row.clone();
					row.children().each(function(index) {
						clone.children().eq(index).outerWidth(jQuery(this).outerWidth());
					});
					clone.width(row.width()).height(row.height());
					return clone;
				},
				start(event, ui) {
					ui.placeholder.height(ui.item.outerHeight());
				},
				update: refresh,
			});
		}
		refresh();
	}
	const deletion = document.getElementById('onyx-delete_on_uninstall');
	if (deletion) {
		const warning = document.getElementById('onyx-uninstall-warning');
		const ack = document.getElementById('onyx-uninstall-ack');
		const refresh = () => {
			const enabled = deletion.value === '1';
			warning.hidden = ! enabled;
			ack.hidden = ! enabled;
			ack.querySelector('input').required = enabled;
		};
		deletion.addEventListener('change', refresh);
		refresh();
	}
}());
