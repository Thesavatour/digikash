<div class="modal fade urk-modal" id="edit_user_rank_modal" tabindex="-1" aria-labelledby="editUserRankModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            {{-- Modal Header --}}
            <div class="modal-header urk-modal__header">
                <span class="urk-modal__header-icon"><i class="fa-solid fa-award"></i></span>
                <div class="urk-modal__header-text">
                    <span class="urk-modal__eyebrow">{{ __('Progression Tier') }}</span>
                    <h5 class="urk-modal__title" id="editUserRankModalLabel">{{ __('Edit User Rank') }}</h5>
                </div>
                <button type="button" class="urk-modal__close" data-coreui-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            {{-- Modal Body (AJAX loaded) --}}
            <div class="modal-body" id="edit_user_rank_data"></div>
        </div>
    </div>
</div>
