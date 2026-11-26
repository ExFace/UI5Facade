/**
 * Exercises real Dexie/IndexedDB storage in an isolated database, without touching user preferences.
 */
export async function runUI5SetupStorageRegression() {
    const oManager = globalThis.exfSetupManager;
    const sOriginalDbName = oManager._dexieDbConfig.name;
    const sOriginalInstallation = oManager._sInstallationName;
    const sDbName = 'exf-ui5-setups-test-' + crypto.randomUUID();
    const aKey = ['test-screen', 'test-table', 'test-object'];
    const oLegacySetup = {
        slug: aKey[0],
        widget_id: aKey[1],
        object_id: aKey[2],
        setup_uid: 'legacy',
        setup_uxon: '{"columns":[]}',
        setup_name: 'Existing setup',
        date_last_applied: '2026-01-01T00:00:00.000Z'
    };
    let iChecks = 0;
    const fnCheck = (bCondition, sMessage) => {
        if (!bCondition) {
            throw new Error(sMessage);
        }
        iChecks++;
    };
    const fnSave = sUid => oManager.dexie.saveLastAppliedSetup(...aKey, sUid, '{"columns":[]}', sUid);
    const fnRead = () => oManager.dexie.getCurrentSetup(...aKey);

    oManager._dexieDbConfig.name = sDbName;
    try {
        const oLegacyDb = new Dexie(sDbName);
        try {
            oLegacyDb.version(3).stores({setups: '[slug+widget_id+object_id], setup_uid, date_last_applied'});
            await oLegacyDb.setups.put(oLegacySetup);
        } finally {
            oLegacyDb.close();
        }

        oManager.init('dev');
        const oImported = await fnRead();
        fnCheck(oImported.setup_uid === 'legacy', 'Legacy setup must be applied on first load.');
        fnCheck(oImported.setup_uxon === oLegacySetup.setup_uxon
            && oImported.setup_name === oLegacySetup.setup_name
            && oImported.date_last_applied === oLegacySetup.date_last_applied, 'Import must preserve the payload and metadata.');
        await fnSave('dev-setup');

        oManager.init('prod');
        fnCheck((await fnRead()).setup_uid === 'legacy', 'Another installation must inherit legacy data, not new dev data.');
        await fnSave('prod-setup');
        oManager.init('dev');
        fnCheck((await fnRead()).setup_uid === 'dev-setup', 'Applying in prod must not overwrite dev.');
        fnCheck(await oManager.dexie.getCurrentSetup(aKey[0], 'other-widget', aKey[2]) === undefined,
            'Widget identity must remain part of the key.');
        fnCheck(await oManager.dexie.getCurrentSetup('other-screen', aKey[1], aKey[2]) === undefined,
            'Screen identity must remain part of the key.');
        fnCheck(await oManager.dexie.getCurrentSetup(aKey[0], aKey[1], 'other-object') === undefined,
            'Object identity must remain part of the key.');
        fnCheck((await oManager.getSetupProperty(...aKey, null, 'setup_uxon')).columns.length === 0,
            'Auto-apply must still read and parse the scoped payload.');

        await oManager.dexie.deleteCurrentSetup(...aKey);
        oManager.init('dev');
        fnCheck(await fnRead() === undefined, 'Reset must survive reinitialization without restoring legacy data.');
        oManager.init('prod');
        fnCheck((await fnRead()).setup_uid === 'prod-setup', 'Reset in dev must not clear prod.');

        oManager.init('reset-before-read');
        await oManager.dexie.deleteCurrentSetup(...aKey);
        fnCheck(await fnRead() === undefined, 'Reset before the first read must not resurrect legacy data.');
        oManager.init('save-before-read');
        await fnSave('new-setup');
        fnCheck((await fnRead()).setup_uid === 'new-setup', 'First save must take precedence over legacy data.');

        oManager.init('concurrent');
        await Promise.all([fnRead(), fnRead(), oManager.dexie.deleteCurrentSetup(...aKey)]);
        fnCheck(await fnRead() === undefined, 'Concurrent migration and reset must not resurrect data.');

        oManager.init('capture-dev');
        const oDevSave = fnSave('captured-dev');
        oManager.init('capture-prod');
        const oProdSave = fnSave('captured-prod');
        await Promise.all([oDevSave, oProdSave]);
        oManager.init('capture-dev');
        fnCheck((await fnRead()).setup_uid === 'captured-dev', 'Pending operations must retain their installation identity.');
        oManager.init('capture-prod');
        fnCheck((await fnRead()).setup_uid === 'captured-prod', 'Concurrent installations must remain isolated.');

        oManager.init('failed-import');
        let bRejected = false;
        try {
            await oManager._withSetupsDb(() => Promise.reject(new Error('Test transaction rollback')));
        } catch (oError) {
            bRejected = oError.message === 'Test transaction rollback';
        }
        fnCheck(bRejected, 'Storage transaction failures must reject.');
        const oDb = oManager._oOpenSetupsDb();
        try {
            fnCheck(await oDb.installations.get('failed-import') === undefined, 'Failed imports must not be marked complete.');
            fnCheck(await oDb.current_setups.get(['failed-import', ...aKey]) === undefined, 'Failed imports must roll back copied data.');
            fnCheck((await oDb.setups.get(aKey)).setup_uid === 'legacy', 'Legacy data must remain unchanged.');
        } finally {
            oDb.close();
        }
        fnCheck((await fnRead()).setup_uid === 'legacy', 'A failed import must be retried successfully.');
        return {checks: iChecks, passed: true};
    } finally {
        oManager._dexieDbConfig.name = sOriginalDbName;
        oManager._sInstallationName = sOriginalInstallation;
        await Dexie.delete(sDbName);
    }
}
