<div class="x_panel">
    <div class="x_title">
        <h2>List Member</h2>
        <div class="clearfix"></div>
    </div>

    <div class="x_content">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-button">
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Member Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list_member as $val): ?>
                        <tr>
                            <td><?= esc($val->member_id) ?></td>
                            <td><?= esc($val->member_name) ?></td>
                            <td><?= esc($val->member_type_name) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>