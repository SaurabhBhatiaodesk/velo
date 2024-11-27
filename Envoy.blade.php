@servers(['web' => 'remote_username@remote_host'])

@task('list', ['on' => 'web'])
    ls -l
@endtask
