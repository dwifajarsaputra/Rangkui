<h5 class="bd-wizard-step-title">Configuration Database</h5>
<h2 class="section-heading">Configuration Database</h2>
<div class="row">
    <div class="col-md-8">
        <div class="form-group">
            <label for="hostName" class="sr-only">Host</label>
            <input type="text" name="host" id="hostName" class="form-control" placeholder="Host Database" data-config="db" value="<?= esc($hostName ?? "localhost") ?>">
        </div>
        <div class="form-group">
            <label for="dbName" class="sr-only">Database</label>
            <input type="text" name="database" id="dbName" class="form-control" placeholder="Database Name" data-config="db" value="<?= esc($dbName ?? "rangkui") ?>">
        </div>
        <div class="form-group">
            <label for="userName" class="sr-only">Username</label>
            <input type="text" name="username" id="userName" class="form-control" placeholder="Username Database" data-config="db" value="<?= esc($userName ?? "root") ?>">
        </div>
        <div class="form-group">
            <label for="password" class="sr-only">Password</label>

            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password Database" data-config="db" value="<?= esc($password ?? "") ?>">
                <span class="input-group-append" id="show-password" title="Tampilkan password" data-bs-toggle="tooltip">
                    <span class="input-group-text" id="show">
                        <i class="fa fa-eye"></i>
                    </span>
                </span>
            </div>
        </div>
        <div class="form-group">
            <label for="port" class="sr-only">Port</label>
            <input type="number" name="port" id="port" class="form-control" placeholder="Port Database" value="3306" data-config="db">
        </div>
        <hr>
        <h6 class="mb-3">Akun Administrator</h6>
        <div class="form-group">
            <label for="adminUsername" class="sr-only">Username Admin</label>
            <input type="text" name="admin_username" id="adminUsername" class="form-control" placeholder="Username Admin" data-config="db" value="admin" autocomplete="off" required>
        </div>
        <div class="form-group">
            <label for="adminPassword" class="sr-only">Password Admin</label>
            <input type="password" name="admin_password" id="adminPassword" class="form-control" placeholder="Password Admin (min. 8 karakter)" data-config="db" minlength="8" autocomplete="new-password" required>
            <small class="form-text text-muted">Wajib diisi. Rangkui tidak lagi memakai password bawaan.</small>
        </div>
    </div>
</div>