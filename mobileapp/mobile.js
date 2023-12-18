
this.saveChanges = (cmId, itemId, state) => {
    return this.CoreDomUtilsProvider.showModalLoading('core.sending', true).then((modal) => {
        const site = this.CoreSitesProvider.getCurrentSite();

        return site.write('mod_checklist_update_item_state', {
            cmid: cmId,
            itemid: itemId,
            state: state.detail.checked,
        }).then(() => {
            // updateCachedContent was introduced in the 4.4 version of the app, use refreshContent if not available.
            this.updateCachedContent ? this.updateCachedContent() : this.refreshContent();
        }).catch(error => {
            this.CoreDomUtilsProvider.showErrorModal(error);
        }).finally(() => {
            modal.dismiss();
        });
    });

};
