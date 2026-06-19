/* global jQuery, loProData */
(function ($) {
	'use strict';

	var ruleIndex = 0;

	// ── Init ──────────────────────────────────────────────────────────────────
	$(function () {

		// Count existing rules to set the next index
		ruleIndex = $('#lo-rules-container .lo-rule-row').length;

		// Populate term dropdowns for existing rules
		$('#lo-rules-container .lo-rule-row').each(function () {
			var $row      = $(this);
			var $typeSelect = $row.find('.lo-rule-type-select');
			var $termSelect = $row.find('.lo-term-select');
			var type        = $typeSelect.val();
			var selected    = $termSelect.data('selected');
			populateTerms( $termSelect, type, selected );
		});

		// ── Add rule button ──────────────────────────────────────────────────
		$('#lo-add-rule').on('click', function () {
			var tmpl = $('#lo-rule-tmpl').html();
			if ( ! tmpl ) return;

			tmpl = tmpl.replace( /__IDX__/g, ruleIndex );

			var $newRow = $( tmpl );
			$('#lo-empty-msg').hide();
			$('#lo-rules-container').append( $newRow );

			// Populate category terms by default
			var $termSelect = $newRow.find('.lo-term-select');
			populateTerms( $termSelect, 'category', '' );

			ruleIndex++;
		});

		// ── Remove rule (delegated) ──────────────────────────────────────────
		$(document).on('click', '.lo-remove-rule', function () {
			var $row = $(this).closest('.lo-rule-row');
			if ( ! window.confirm( loProData.strings.confirmRemove ) ) return;
			$row.fadeOut(180, function () {
				$(this).remove();
				if ( ! $('#lo-rules-container .lo-rule-row').length ) {
					$('#lo-empty-msg').show();
				}
			});
		});

		// ── Rule type change — repopulate terms ──────────────────────────────
		$(document).on('change', '.lo-rule-type-select', function () {
			var $row  = $(this).closest('.lo-rule-row');
			var $term = $row.find('.lo-term-select');
			populateTerms( $term, $(this).val(), '' );
		});

	});

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Populate a term <select> with categories or tags.
	 *
	 * @param {jQuery} $sel      The <select> element.
	 * @param {string} type      'category' or 'tag'.
	 * @param {string} selected  Pre-selected term ID.
	 */
	function populateTerms( $sel, type, selected ) {
		var terms = type === 'tag' ? loProData.tags : loProData.categories;
		var html  = '<option value="">' + loProData.strings.selectTerm + '</option>';

		$.each( terms, function ( i, term ) {
			var sel = String( term.id ) === String( selected ) ? ' selected' : '';
			html += '<option value="' + term.id + '"' + sel + '>' + escHtml( term.name ) + '</option>';
		});

		$sel.html( html );
	}

	/** Minimal HTML escape for injected term names */
	function escHtml( str ) {
		return String( str )
			.replace( /&/g,  '&amp;'  )
			.replace( /</g,  '&lt;'   )
			.replace( />/g,  '&gt;'   )
			.replace( /"/g,  '&quot;' )
			.replace( /'/g,  '&#039;' );
	}

}(jQuery));
