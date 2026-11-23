<?php extract($list_var); ?>
<div class="x_panel">
    <div class="x_title">
        <h2>Modify Global Application Preferences</h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">

        <form name="mainForm" id="mainForm" method="post" action="/sistem/pengaturan-sistem/create">
            <?= csrf_field(); ?>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="library_name">Library Name</label>
                    <input type="text" class="form-control" name="library_name" id="library_name" value="<?= esc((string) @unserialize($library_name, ['allowed_classes' => ['stdClass']])); ?>" tabindex="1">
                </div>


                <div class="form-group">
                    <label for="default_lang">Default Application Language</label>
                    <select class="form-control select2" name="default_lang" id="default_lang" tabindex="3" data-placeholder="--Please Select Language--" data-allow-clear="true">
                        <option></option>
                        <?php $lang = unserialize($default_lang, ['allowed_classes' => ['stdClass']]) ?>
                        <?php foreach ($list_lang as $k => $val): ?>
                            <option value="<?= esc($val->language_id) ?>" <?= $lang == $val->language_id ? "selected" : "" ?>><?= esc($val->language_name) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="session_timeout">Session Expired</label>
                    <input type="number" class="form-control" name="session_timeout" id="session_timeout" value="<?= esc((string) @unserialize($session_timeout, ['allowed_classes' => ['stdClass']])); ?>" tabindex="5">
                </div>

                <div class="form-group">
                    <label for="spellchecker_enabled">Enable Spell Checker</label>
                    <select class="form-control select2" name="spellchecker_enabled" id="spellchecker_enabled" tabindex="11">
                        <?php $spel_chek = unserialize($spellchecker_enabled, ['allowed_classes' => ['stdClass']]) ?>
                        <option value="0" <?= $spel_chek == 0 ? "selected" : ""; ?>>Impossible</option>
                        <option value="1" <?= $spel_chek == 1 ? "selected" : ""; ?>>Possible</option>
                    </select>
                </div>

                <?php
                $barcode_encode = unserialize($barcode_encoding, ['allowed_classes' => ['stdClass']]);

                $options = [
                    "ISBN"   => "isbn numbers (still EAN-13)",
                    "39"     => "code 39",
                    "128"    => "code 128",
                    "128C"   => "code 128 (compact form for digits)",
                    "128B"   => "code 128, full printable ascii",  // selected option
                    "I25"    => "interleaved 2 of 5",
                    "128RAW" => "Raw code 128",
                    "CBR"    => "Codabars",
                    "MSI"    => "MSI",
                    "PLS"    => "Plesseys",
                    "93"     => "code 93"
                ];
                ?>
                <div class="form-group">
                    <label for="barcode_encoding">Barcode Encoding</label>
                    <select class="form-control select2" name="barcode_encoding" id="barcode_encoding" tabindex="12" data-placeholder="--Choose Barcode Encoding--">
                        <option></option>
                        <?php foreach ($options as $key => $val) : ?>
                            <option value="<?= esc($key) ?>" <?= ($barcode_encode == $key) ? "selected" : ""; ?>><?= esc($val) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <?php
                $recaptcha = unserialize($recaptcha, ['allowed_classes' => ['stdClass']]);
                $recaptcha_admin = $recaptcha['smc'];
                $recaptcha_member = $recaptcha['member'];
                ?>
                <div class="form-group">
                    <label for="enable_recaptcha_admin">Recaptcha Admin</label>
                    <select class="form-control select2" name="enable_recaptcha_admin" id="enable_recaptcha_admin" tabindex="14">
                        <option value="0" <?= $recaptcha_admin == 0 ? "selected" : ""; ?>>Impossible</option>
                        <option value="1" <?= $recaptcha_admin == 1 ? "selected" : ""; ?>>Possible</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="enable_recaptcha_member">Recaptcha Member</label>
                    <select class="form-control select2" name="enable_recaptcha_member" id="enable_recaptcha_member" tabindex="15">
                        <option value="0" <?= $recaptcha_member == 0 ? "selected" : ""; ?>>Impossible</option>
                        <option value="1" <?= $recaptcha_member == 1 ? "selected" : ""; ?>>Possible</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="enable_recaptcha_key">Recaptcha API Key</label>
                    <div>
                        <a href="#" data-toggle="modal" data-target="#keyCaptcha" title="Recaptcha API Key" class="btn btn-sm border" tabindex="16"><i class="fa fa-key"></i></a>
                        <a href="#" data-toggle="modal" data-target="#manualPageModal" title="Manual Page" class="btn btn-sm border"><i class="fa fa-question"></i></a>
                        <!-- <a href="/admin/modules/system/pop_recaptcha.php?type=manual_page" class="btn btn-sm border openPopUp" title="Manual Page" tabindex="17"><i class="fa fa-question"></i></a> -->
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-sm" name="updateData" tabindex="18">Save Configuration</button>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="library_subname">Additional Library Name</label>
                    <input type="text" class="form-control" name="library_subname" id="library_subname" value="<?= esc((string) @unserialize($library_subname, ['allowed_classes' => ['stdClass']])); ?>" tabindex="2">
                </div>

                <?php
                $options = [
                    "10" => "10",
                    "20" => "20",
                    "30" => "30",
                    "40" => "40",
                    "50" => "50"
                ];
                $opac_result = unserialize($opac_result_num, ['allowed_classes' => ['stdClass']]);
                ?>
                <div class="form-group">
                    <label for="opac_result_num">Number of Collections Displayed in OPAC Search Results</label>
                    <select class="form-control select2" name="opac_result_num" id="opac_result_num" tabindex="4" data-placeholder="--Select Opac Result--">
                        <option></option>
                        <?php foreach ($options as $val) : ?>
                            <option value="<?= esc($val) ?>" <?= $opac_result == $val ? "selected" : ""; ?>><?= esc($val) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>


                <div class="form-group">
                    <label for="enable_xml_detail">Deteil XML OPAC</label>
                    <select class="form-control select2" name="enable_xml_detail" id="enable_xml_detail" tabindex="6">
                        <option value="0" <?= unserialize($enable_xml_detail, ['allowed_classes' => ['stdClass']]) == 0 ? "selected" : ""; ?>>Impossible</option>
                        <option value="1" <?= unserialize($enable_xml_detail, ['allowed_classes' => ['stdClass']]) == 1 ? "selected" : ""; ?>>Possible</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="enable_xml_result">Results XML OPAC</label>
                    <select class="form-control select2" name="enable_xml_result" id="enable_xml_result" tabindex="7">
                        <option value="0" <?= unserialize($enable_xml_result, ['allowed_classes' => ['stdClass']]) == 0 ? "selected" : ""; ?>>Impossible</option>
                        <option value="1" <?= unserialize($enable_xml_result, ['allowed_classes' => ['stdClass']]) == 1 ? "selected" : ""; ?>>Possible</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="allow_file_download">Enable OPAC File Download</label>
                    <select class="form-control select2" name="allow_file_download" id="allow_file_download" tabindex="8">
                        <option value="0" <?= unserialize($allow_file_download, ['allowed_classes' => ['stdClass']]) == 0 ? "selected" : ""; ?>>Not Allowed</option>
                        <option value="1" <?= unserialize($allow_file_download, ['allowed_classes' => ['stdClass']]) == 1 ? "selected" : ""; ?>>Allowed</option>
                    </select>
                </div>


                <div class="form-group">
                    <label for="enable_promote_titles">Show Selected Titles on the OPAC Page</label>
                    <div class="radio">
                        <label>
                            <input type="radio" name="enable_promote_titles" value="1" tabindex="9" <?= unserialize($enable_promote_titles, ['allowed_classes' => ['stdClass']]) == 1 ? "checked" : ""; ?>> Yes
                            <input type="radio" name="enable_promote_titles" value="0" tabindex="10" <?= unserialize($enable_promote_titles, ['allowed_classes' => ['stdClass']]) == 0 ? "checked" : ""; ?>> No
                        </label>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>

<?php
global $contentPath;

echo view($contentPath . 'Sistem\modal\v_recaptcha_page');
echo view($contentPath . 'Sistem\modal\v_manual_page');
?>