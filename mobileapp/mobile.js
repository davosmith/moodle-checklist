
this.saveChanges = (cmId, itemId, state) => {
    return this.CoreDomUtilsProvider.showModalLoading('core.sending', true).then((modal) => {
        const site = this.CoreSitesProvider.getCurrentSite();

        return site.write('mod_checklist_update_item_state', {
            cmid: cmId,
            itemid: itemId,
            state: state.detail.checked,
        }).then(() => {
            // Ideally refresh on page load. This is causing a flicker.
            this.refreshContent();
        }).catch(error => {
            this.CoreDomUtilsProvider.showErrorModal(error);
        }).finally(() => {
            modal.dismiss();
        });
    });

};
